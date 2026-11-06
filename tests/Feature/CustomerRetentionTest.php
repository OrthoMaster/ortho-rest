<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Models\ReservationEdit;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Support\CustomerRetention;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use ZipArchive;

class CustomerRetentionTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-12-01 09:00'));

        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'reservation');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->base = "/{$this->restaurant->slug}/admin/reservations";
        $this->actingAs($this->owner);
    }

    private function customer(string $name, string $phone, string $lastVisit, ?string $notes = null): Customer
    {
        $this->actingInTenant($this->restaurant);
        $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'@mail.test']);
        $customer->forceFill(['created_at' => '2026-01-01 10:00'])->save();

        $reservation = Reservation::create([
            'customer_id' => $customer->id,
            'area_id' => Area::first()->id,
            'reserved_at' => $lastVisit,
            'ends_at' => $lastVisit,
            'party_size' => 2,
            'status' => ReservationStatus::Completed,
            'source' => ReservationSource::Phone,
            'notes' => $notes,
        ]);

        ReservationEdit::create(['restaurant_id' => $this->restaurant->id, 'reservation_id' => $reservation->id, 'changes' => ["Khách: $name $phone → $name $phone", 'Số khách: 2 → 3']]);
        ActivityLog::record($this->restaurant->id, 'reservation', "Thêm đặt bàn của $name", ["SĐT $phone"], $reservation);

        return $customer;
    }

    public function test_customers_past_the_limit_are_anonymized_when_the_admin_is_opened(): void
    {
        $old = $this->customer('Chị Hoa', '0901111111', '2026-09-30 19:00', 'Dị ứng tôm');
        $recent = $this->customer('Anh Long', '0902222222', '2026-10-05 19:00');
        $returning = $this->customer('Cô Ba', '0903333333', '2026-08-01 19:00');
        $this->customer('Cô Ba', '0903333333', '2026-12-20 19:00');

        $this->get("/{$this->restaurant->slug}/admin")->assertOk();

        $this->actingInTenant($this->restaurant);
        $old->refresh();
        $this->assertNotNull($old->anonymized_at);
        $this->assertSame(__('customers.anonymous'), $old->name);
        $this->assertNull($old->phone);
        $this->assertNull($old->email);
        $this->assertNull($recent->fresh()->anonymized_at);
        $this->assertNull($returning->fresh()->anonymized_at, 'Khách còn lượt đặt sắp tới thì chưa xoá');

        // Lượt đặt vẫn còn nhưng không còn ghi chú, lịch sử và nhật ký không còn tên, số.
        $reservation = Reservation::where('customer_id', $old->id)->sole();
        $this->assertNull($reservation->notes);
        $this->assertSame(['Khách: Khách đã ẩn danh Khách đã ẩn danh → Khách đã ẩn danh Khách đã ẩn danh', 'Số khách: 2 → 3'], $reservation->edits()->sole()->changes);
        $logs = ActivityLog::where('subject_type', $reservation->getMorphClass())->where('subject_id', $reservation->id)->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        foreach ($logs as $log) {
            $text = $log->summary.json_encode($log->changes, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('Chị Hoa', $text);
            $this->assertStringNotContainsString('0901111111', $text);
        }

        $this->assertDatabaseHas('activity_logs', ['restaurant_id' => $this->restaurant->id, 'area' => 'customers', 'user_id' => $this->owner->id]);
        $this->assertSame(1, ActivityLog::where('area', 'customers')->count());

        // Mỗi ngày dọn một lần.
        $this->customer('Bác Tư', '0904444444', '2026-09-01 19:00');
        $this->get("/{$this->restaurant->slug}/admin")->assertOk();
        $this->assertNull(Customer::where('phone', '0904444444')->sole()->anonymized_at);
        $this->travelTo(Carbon::parse('2026-12-02 09:00'));
        $this->get("/{$this->restaurant->slug}/admin")->assertOk();
        $this->assertSame(0, Customer::where('phone', '0904444444')->count());
    }

    public function test_owner_sets_the_retention_in_general_settings(): void
    {
        $this->get("/{$this->restaurant->slug}/admin/restaurant")
            ->assertSee(__('customers.retention_title'))
            ->assertSee('name="customer_retention_days" value="60" checked', false);

        $this->put("/{$this->restaurant->slug}/admin/restaurant/customer-data", ['customer_retention_days' => 45])->assertSessionHasErrors('customer_retention_days');
        $this->put("/{$this->restaurant->slug}/admin/restaurant/customer-data", ['customer_retention_days' => 30])->assertSessionHasNoErrors();
        $this->assertSame(30, $this->restaurant->fresh()->customer_retention_days);

        $customer = $this->customer('Anh Long', '0902222222', '2026-10-25 19:00');
        Cache::flush();
        $this->get("/{$this->restaurant->slug}/admin")->assertOk();
        $this->assertNotNull($customer->fresh()->anonymized_at);

        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager]);
        $this->actingAs($manager)->put("/{$this->restaurant->slug}/admin/restaurant/customer-data", ['customer_retention_days' => 90])->assertForbidden();
    }

    public function test_list_shows_the_delete_date_and_warns_before(): void
    {
        $this->customer('Chị Hoa', '0901111111', '2026-10-05 19:00');
        $this->customer('Anh Long', '0902222222', '2026-11-20 19:00');

        $this->get("$this->base/customers")
            ->assertSee(__('customers.delete_date'))
            ->assertSee('04/12/2026')
            ->assertSee('19/01/2027')
            ->assertSee(__('customers.soon', ['count' => 1, 'days' => CustomerRetention::NOTICE_DAYS]))
            ->assertSee(__('customers.export'))
            ->assertSee(__('customers.anonymize'));
    }

    public function test_owner_exports_the_current_list_as_excel_and_nothing_is_kept(): void
    {
        $this->customer('Chị Hoa', '0901111111', '2026-11-20 19:00');
        $anonymous = $this->customer('Anh Long', '0902222222', '2026-11-20 19:00');
        CustomerRetention::anonymize($anonymous);

        $response = $this->get("$this->base/customers/export")->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('khach-hang-2026-12-01.xlsx', $response->headers->get('Content-Disposition'));

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('Chị Hoa', $sheet);
        $this->assertStringContainsString('0901111111', $sheet);
        $this->assertStringContainsString(__('customers.delete_date'), $sheet);
        $this->assertStringNotContainsString(__('customers.anonymous'), $sheet);
        $this->assertSame(0, ActivityLog::where('area', 'customers')->count(), 'Xuất file không ghi lại gì');
    }

    public function test_owner_can_anonymize_one_customer_now(): void
    {
        $customer = $this->customer('Chị Hoa', '0901111111', '2026-11-20 19:00');

        $this->get("$this->base/customers")->assertSee('data-confirm="'.e(__('customers.anonymize_confirm', ['name' => 'Chị Hoa'])).'"', false);
        $this->post("$this->base/customers/{$customer->id}/anonymize")->assertRedirect();

        $this->actingInTenant($this->restaurant);
        $this->assertNotNull($customer->fresh()->anonymized_at);
        $this->get("$this->base/customers")->assertDontSee('Chị Hoa')->assertDontSee(__('customers.anonymous'));
        $this->post("$this->base/customers/{$customer->id}/anonymize")->assertNotFound();
    }

    public function test_only_the_owner_exports_or_anonymizes(): void
    {
        $customer = $this->customer('Chị Hoa', '0901111111', '2026-11-20 19:00');
        $staff = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Staff]);

        $this->actingAs($staff)->get("$this->base/customers")->assertOk()->assertDontSee(__('customers.export'))->assertDontSee(__('customers.anonymize'));
        $this->actingAs($staff)->get("$this->base/customers/export")->assertForbidden();
        $this->actingAs($staff)->post("$this->base/customers/{$customer->id}/anonymize")->assertForbidden();

        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'reservation');
        $otherOwner = User::factory()->owner($other)->create();
        $this->actingAs($otherOwner)->post("/{$other->slug}/admin/reservations/customers/{$customer->id}/anonymize")->assertNotFound();
        $this->assertNull($customer->fresh()->anonymized_at);
    }
}

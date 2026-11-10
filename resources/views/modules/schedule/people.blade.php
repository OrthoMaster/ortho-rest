{{--
    Bảng nhân viên nổi bên phải màn hình, chia theo bộ phận (bấm tên bộ phận để đóng mở).
    Nút trên cùng: hiện / ẩn số ca và giờ cạnh tên, thu nhỏ bảng để nhìn lịch.
    Mỗi người một dòng, không xuống dòng, không cuộn ngang.
--}}
@php($groups = $users->groupBy(fn ($user) => $user->department_id ?? 0))
<aside class="people-card" data-people-card aria-label="{{ __('schedule.people') }}">
    <div class="people-card-head">
        <strong>{{ __('schedule.people') }}</strong>
        <span class="people-card-tools">
            <button class="row-btn" type="button" data-toggle-stats aria-pressed="false" title="{{ __('schedule.show_stats') }}" aria-label="{{ __('schedule.show_stats') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </button>
            <button class="row-btn" type="button" data-minimize title="{{ __('schedule.minimize') }}" aria-label="{{ __('schedule.minimize') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg>
            </button>
        </span>
    </div>
    <div class="people-card-body">
        <small class="section-hint">{{ __('schedule.people_hint') }}</small>
        @foreach ($groups as $departmentId => $members)
            @php($department = $members->first()->department)
            <details class="people-group" open data-people-group="{{ $departmentId }}">
                <summary>
                    <span class="tone-dot tone-{{ $department?->tone ?? 0 }}" aria-hidden="true"></span>
                    {{ $department?->name ?? __('staff.no_department') }}
                    <small>{{ $members->count() }}</small>
                </summary>
                <ul class="people-list">
                    @foreach ($members as $person)
                        <li @class(['is-over' => $stats[$person->id]['over'] ?? false]) data-person="{{ $person->id }}" title="{{ $person->name }} · {{ $stats[$person->id]['label'] ?? '' }}">
                            <span class="person-pill tone-{{ $department?->tone ?? 0 }}" draggable="true" data-drag-user="{{ $person->id }}" data-name="{{ short_name($person->name) }}" data-full="{{ $person->name }}" role="button" tabindex="0">{{ short_name($person->name) }}</span>
                            <small class="person-stat" data-stat="{{ $person->id }}">{{ $stats[$person->id]['short'] ?? '' }}</small>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </div>
</aside>
<button class="people-card-open" type="button" data-people-open hidden>{{ __('schedule.people') }}</button>

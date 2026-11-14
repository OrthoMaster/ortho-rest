/*
 * Bộ đặt bàn của Liresto: chèn vào <div data-liresto-when> trong <form data-liresto-booking>
 * phần chọn số người, ngày, khu vực, giờ còn trống, rồi gửi đặt bàn về Liresto.
 * Dùng chung cho trang đặt bàn mẫu và trang quán tự tạo (trang tự tạo chạy cô lập:
 * không cookie, không localStorage, chỉ gọi được về Liresto).
 */
(function () {
    'use strict';

    var config = window.LIRESTO_BOOKING || {};
    var form = document.querySelector('form[data-liresto-booking]');
    var mount = form && form.querySelector('[data-liresto-when]');

    if (!form || !mount || !config.info) {
        return;
    }

    var info = null;
    var text = {};
    var state = { party: 2, date: null, time: null, month: null };
    var request = 0;

    var el = function (tag, className, content) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (content !== undefined) {
            node.textContent = content;
        }

        return node;
    };

    var hidden = function (name, value) {
        var input = form.querySelector('input[type=hidden][name="' + name + '"]');

        if (!input) {
            input = el('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }

        input.value = value === null || value === undefined ? '' : value;
    };

    var result = function (message, kind) {
        var box = form.querySelector('[data-liresto-result]') || document.querySelector('[data-liresto-result]');

        if (!box) {
            box = el('div');
            box.setAttribute('data-liresto-result', '');
            form.appendChild(box);
        }

        box.className = 'lb-result' + (kind ? ' is-' + kind : '');
        box.textContent = message || '';
        box.hidden = !message;
        box.setAttribute('role', kind === 'error' ? 'alert' : 'status');

        if (message) {
            box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    };

    var clearErrors = function () {
        form.querySelectorAll('.lb-error').forEach(function (node) {
            node.remove();
        });
        form.querySelectorAll('[aria-invalid]').forEach(function (node) {
            node.removeAttribute('aria-invalid');
        });
    };

    var showError = function (field, message) {
        var target = form.querySelector('[name="' + field + '"]:not([type=hidden])');
        var anchor = target || (field === 'time' || field === 'party_size' ? mount.querySelector('.lb-times') : (field === 'date' ? mount.querySelector('.lb-date') : null));
        var error = el('p', 'lb-error', message);

        if (target) {
            target.setAttribute('aria-invalid', 'true');
        }

        if (anchor) {
            anchor.insertAdjacentElement('afterend', error);
        } else {
            result(message, 'error');
        }
    };

    // Phần chọn: số người, ngày, khu vực, giờ.
    var partyValue = el('output', 'lb-party-value');
    var dateButton = el('button', 'lb-date');
    var calendar = el('div', 'lb-calendar');
    var times = el('div', 'lb-times');
    var openDays = {};

    var field = function (label, body) {
        var wrap = el('div', 'lb-field');
        wrap.appendChild(el('span', 'lb-label', label));
        wrap.appendChild(body);

        return wrap;
    };

    var chip = function (label, sub, selected, disabled, onClick, className) {
        var button = el('button', className || 'lb-chip');
        button.type = 'button';
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        button.disabled = !!disabled;

        if (sub) {
            button.appendChild(el('span', 'lb-chip-top', label));
            button.appendChild(el('span', 'lb-chip-sub', sub));
        } else {
            button.textContent = label;
        }

        button.addEventListener('click', onClick);

        return button;
    };

    var renderParty = function () {
        partyValue.textContent = state.party + ' ' + text.people;
        hidden('party_size', state.party);
        minus.disabled = state.party <= 1;
        plus.disabled = state.party >= info.max_party;
    };

    var minus = chip('−', null, false, false, function () {
        state.party = Math.max(1, state.party - 1);
        renderParty();
        loadSlots();
    }, 'lb-step');
    var plus = chip('+', null, false, false, function () {
        state.party = Math.min(info.max_party, state.party + 1);
        renderParty();
        loadSlots();
    }, 'lb-step');

    // Ngày: bấm nút thì mở lịch tháng; ngày quán nhận đặt thì bấm được, còn lại mờ.
    var pad = function (number) {
        return (number < 10 ? '0' : '') + number;
    };
    var iso = function (date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    };
    var parse = function (value) {
        var parts = value.split('-');

        return new Date(+parts[0], +parts[1] - 1, +parts[2]);
    };

    var renderDateButton = function () {
        var day = state.date ? openDays[state.date] : null;

        dateButton.textContent = '';
        dateButton.appendChild(el('span', 'lb-date-text', day ? day.long + ', ' + day.day : text.pick_date));
        dateButton.setAttribute('aria-expanded', calendar.hidden ? 'false' : 'true');
        dateButton.classList.toggle('is-empty', !day);
        hidden('date', state.date);
    };

    var renderCalendar = function () {
        var first = info.days[0] ? parse(info.days[0].date) : new Date();
        var last = info.days.length ? parse(info.days[info.days.length - 1].date) : first;
        var month = state.month || new Date(first.getFullYear(), first.getMonth(), 1);
        var head = el('div', 'lb-cal-head');
        var prev = el('button', 'lb-cal-nav', '‹');
        var next = el('button', 'lb-cal-nav', '›');
        var grid = el('div', 'lb-cal-grid');

        calendar.textContent = '';
        prev.type = next.type = 'button';
        prev.setAttribute('aria-label', text.prev_month);
        next.setAttribute('aria-label', text.next_month);
        prev.disabled = month <= new Date(first.getFullYear(), first.getMonth(), 1);
        next.disabled = new Date(month.getFullYear(), month.getMonth() + 1, 1) > last;
        prev.addEventListener('click', function () {
            state.month = new Date(month.getFullYear(), month.getMonth() - 1, 1);
            renderCalendar();
        });
        next.addEventListener('click', function () {
            state.month = new Date(month.getFullYear(), month.getMonth() + 1, 1);
            renderCalendar();
        });

        head.appendChild(prev);
        head.appendChild(el('strong', 'lb-cal-title', text.month.replace(':month', month.getMonth() + 1).replace(':year', month.getFullYear())));
        head.appendChild(next);
        calendar.appendChild(head);

        text.weekdays.forEach(function (name) {
            grid.appendChild(el('span', 'lb-cal-weekday', name));
        });

        // Lịch bắt đầu từ thứ Hai.
        var offset = (month.getDay() + 6) % 7;

        for (var i = 0; i < offset; i++) {
            grid.appendChild(el('span'));
        }

        for (var date = new Date(month); date.getMonth() === month.getMonth(); date.setDate(date.getDate() + 1)) {
            var key = iso(date);
            var day = openDays[key];
            var button = el('button', 'lb-cal-day', String(date.getDate()));

            button.type = 'button';
            button.disabled = !day || !day.open;
            button.setAttribute('aria-pressed', key === state.date ? 'true' : 'false');
            button.dataset.date = key;

            if (day && day.today) {
                button.classList.add('is-today');
            }

            if (day && day.note) {
                button.title = day.note;
            }

            grid.appendChild(button);
        }

        calendar.appendChild(grid);
    };

    calendar.hidden = true;
    dateButton.type = 'button';
    dateButton.addEventListener('click', function () {
        calendar.hidden = !calendar.hidden;

        if (!calendar.hidden) {
            renderCalendar();
        }

        renderDateButton();
    });
    // Bấm ra ngoài thì đóng lịch.
    document.addEventListener('click', function (event) {
        if (!calendar.hidden && !calendar.contains(event.target) && !dateButton.contains(event.target)) {
            calendar.hidden = true;
            renderDateButton();
        }
    });
    calendar.addEventListener('click', function (event) {
        var button = event.target.closest('.lb-cal-day');

        if (!button || button.disabled) {
            return;
        }

        state.date = button.dataset.date;
        state.time = null;
        calendar.hidden = true;
        clearErrors();
        renderDateButton();
        loadSlots();
    });

    var renderTimes = function (data) {
        times.textContent = '';
        hidden('time', state.time);

        if (!data.any) {
            times.appendChild(el('p', 'lb-empty', data.periods.length ? text.no_slots : text.day_closed));

            return;
        }

        data.periods.forEach(function (period) {
            if (period.name) {
                times.appendChild(el('span', 'lb-period', period.name));
            }

            var grid = el('div', 'lb-grid');

            period.times.forEach(function (slot) {
                var button = chip(slot.time, null, slot.time === state.time, !slot.available, function () {
                    state.time = slot.time;
                    clearErrors();
                    renderTimes(data);
                }, 'lb-time');

                if (!slot.available) {
                    button.title = text.full;
                }

                grid.appendChild(button);
            });
            times.appendChild(grid);
        });
    };

    var loadSlots = function () {
        if (!state.date) {
            times.textContent = '';
            times.appendChild(el('p', 'lb-empty', text.pick_date_first));

            return;
        }

        var mine = ++request;
        times.textContent = '';
        times.appendChild(el('p', 'lb-empty', text.loading));

        var url = config.slots + '?date=' + state.date + '&party_size=' + state.party;

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (mine !== request) {
                    return;
                }

                // Giờ đã chọn mà hết chỗ thì bỏ chọn.
                var still = data.periods.some(function (period) {
                    return period.times.some(function (slot) {
                        return slot.time === state.time && slot.available;
                    });
                });

                if (!still) {
                    state.time = null;
                }

                renderTimes(data);
            })
            .catch(function () {
                times.textContent = '';
                times.appendChild(el('p', 'lb-empty', text.failed));
            });
    };

    var build = function () {
        mount.textContent = '';
        mount.classList.add('lb-when');

        if (!info.online) {
            mount.appendChild(el('p', 'lb-result is-error', info.closed_message));
            form.querySelectorAll('button[type=submit], input[type=submit], button:not([type])').forEach(function (button) {
                button.disabled = true;
            });

            return;
        }

        var party = el('div', 'lb-party');
        party.appendChild(minus);
        party.appendChild(partyValue);
        party.appendChild(plus);
        minus.setAttribute('aria-label', text.fewer);
        plus.setAttribute('aria-label', text.more);

        var when = el('div', 'lb-date-wrap');
        when.appendChild(dateButton);
        when.appendChild(calendar);

        mount.appendChild(field(text.party, party));
        mount.appendChild(field(text.date, when));
        mount.appendChild(field(text.time, times));

        info.days.forEach(function (day) {
            openDays[day.date] = day;
        });
        state.party = Math.min(state.party, info.max_party);

        renderParty();
        renderDateButton();
        loadSlots();

        // Ô bẫy bot: người không thấy, không bấm tới.
        var trap = el('input', 'lb-trap');
        trap.name = 'website';
        trap.tabIndex = -1;
        trap.autocomplete = 'off';
        trap.setAttribute('aria-hidden', 'true');
        form.appendChild(trap);

        hidden('token', info.token);
        hidden('test', config.test || '');

        if (config.test) {
            var banner = el('p', 'lb-test', text.test_banner);
            form.insertBefore(banner, form.firstChild);
        }
    };

    form.setAttribute('novalidate', '');
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();
        result('');

        if (!state.time) {
            showError('time', text.pick_time);

            return;
        }

        var buttons = form.querySelectorAll('button[type=submit], input[type=submit], button:not([type])');
        var labels = [];

        buttons.forEach(function (button, index) {
            labels[index] = button.textContent;
            button.disabled = true;

            if (button.tagName === 'BUTTON') {
                button.textContent = text.sending;
            }
        });

        var done = function () {
            buttons.forEach(function (button, index) {
                button.disabled = false;

                if (button.tagName === 'BUTTON') {
                    button.textContent = labels[index];
                }
            });
        };

        fetch(config.submit, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                });
            })
            .then(function (reply) {
                if (reply.status === 200 && reply.data.ok) {
                    if (reply.data.url) {
                        window.location.href = reply.data.url;

                        return;
                    }

                    done();
                    result(reply.data.message, 'ok');

                    return;
                }

                done();

                var errors = (reply.data && reply.data.errors) || {};
                var keys = Object.keys(errors);

                if (!keys.length) {
                    result((reply.data && reply.data.message) || text.failed, 'error');

                    return;
                }

                keys.forEach(function (key) {
                    if (key === 'token') {
                        result(errors[key][0], 'error');
                    } else {
                        showError(key, errors[key][0]);
                    }
                });

                if (errors.time) {
                    loadSlots();
                }

                var first = form.querySelector('[aria-invalid], .lb-error');

                if (first) {
                    first.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            })
            .catch(function () {
                done();
                result(text.failed, 'error');
            });
    });

    fetch(config.info, { headers: { Accept: 'application/json' } })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            info = data;
            text = data.text || {};
            build();
        })
        .catch(function () {
            mount.textContent = config.failed || '';
        });
})();

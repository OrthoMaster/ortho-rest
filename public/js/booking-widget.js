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
    var state = { party: 2, date: null, area: '', time: null };
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
        var anchor = target || (field === 'time' || field === 'date' || field === 'party_size' || field === 'area_id' ? mount.querySelector('.lb-times') : null);
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
    var days = el('div', 'lb-days');
    var areas = el('div', 'lb-chips');
    var times = el('div', 'lb-times');

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

    var renderDays = function () {
        days.textContent = '';
        info.days.forEach(function (day) {
            var button = chip(day.weekday, day.open ? day.day : text.closed, day.date === state.date, !day.open, function () {
                state.date = day.date;
                state.time = null;
                renderDays();
                loadSlots();
            }, 'lb-chip lb-day');

            if (day.note) {
                button.title = day.note;
            }

            days.appendChild(button);
        });
        hidden('date', state.date);
    };

    var renderAreas = function () {
        areas.textContent = '';
        [{ id: '', name: text.any_area }].concat(info.areas).forEach(function (area) {
            areas.appendChild(chip(area.name, null, String(area.id) === String(state.area), false, function () {
                state.area = String(area.id);
                renderAreas();
                loadSlots();
            }));
        });
        hidden('area_id', state.area);
    };

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
            return;
        }

        var mine = ++request;
        times.textContent = '';
        times.appendChild(el('p', 'lb-empty', text.loading));

        var url = config.slots + '?date=' + state.date + '&party_size=' + state.party + '&area_id=' + state.area;

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

        mount.appendChild(field(text.party, party));
        mount.appendChild(field(text.date, days));

        if (info.areas.length) {
            mount.appendChild(field(text.area, areas));
        }

        mount.appendChild(field(text.time, times));

        state.party = Math.min(state.party, info.max_party);
        state.date = (info.days.find(function (day) {
            return day.open;
        }) || {}).date || null;

        renderParty();
        renderDays();
        renderAreas();
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

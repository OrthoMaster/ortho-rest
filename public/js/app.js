(function () {
    var shell = document.querySelector('.shell');
    var toggle = document.querySelector('.nav-toggle');

    if (shell && toggle) {
        var top = document.querySelector('.sidebar-top');
        var panel = document.getElementById('sidebar-panel');
        var backdrop = document.querySelector('.nav-backdrop');

        var setNavOpen = function (open) {
            shell.classList.toggle('nav-open', open);
            document.body.classList.toggle('no-scroll', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? toggle.dataset.closeLabel : toggle.dataset.openLabel);

            if (open) {
                panel.style.height = window.innerHeight - top.getBoundingClientRect().bottom + 'px';
            } else {
                panel.style.height = '';
            }
        };

        toggle.addEventListener('click', function () {
            setNavOpen(!shell.classList.contains('nav-open'));
        });

        backdrop.addEventListener('click', function () {
            setNavOpen(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && shell.classList.contains('nav-open')) {
                setNavOpen(false);
                toggle.focus();
            }
        });

        window.matchMedia('(min-width: 761px)').addEventListener('change', function () {
            setNavOpen(false);
        });
    }

    // Tìm không dấu: gõ "pho" ra "Phở", gõ "dau" ra "Đậu".
    var plain = function (text) {
        return text.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();
    };

    var search = document.querySelector('[data-search-input]');

    if (search) {
        var groups = Array.prototype.slice.call(document.querySelectorAll('[data-search-group]'));
        var empty = document.querySelector('[data-search-empty]');

        groups.forEach(function (group) {
            group.searchName = plain(group.dataset.searchGroup);
            group.searchItems = Array.prototype.slice.call(group.querySelectorAll('[data-search-item]'));
            group.searchItems.forEach(function (item) {
                item.searchText = plain(item.dataset.searchItem);
            });
            group.searchExtras = Array.prototype.slice.call(group.querySelectorAll('[data-search-hide]'));
        });

        var run = function () {
            var words = plain(search.value).trim().split(/\s+/).filter(Boolean);
            var matches = function (text) {
                return words.every(function (word) {
                    return text.indexOf(word) !== -1;
                });
            };
            var found = 0;

            groups.forEach(function (group) {
                var wholeGroup = matches(group.searchName);
                var visible = 0;

                group.searchItems.forEach(function (item) {
                    var show = wholeGroup || matches(item.searchText);
                    item.hidden = !show;
                    visible += show ? 1 : 0;
                });

                group.hidden = words.length > 0 && visible === 0;
                group.classList.toggle('is-searching', words.length > 0);
                group.searchExtras.forEach(function (extra) {
                    extra.hidden = words.length > 0;
                });
                found += visible;
            });

            empty.hidden = words.length === 0 || found > 0 || groups.length === 0;
        };

        search.addEventListener('input', run);
        run();
    }

    // Đóng mở danh mục, nhớ trạng thái qua các lần tải lại trang.
    var read = function (key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    };
    var write = function (key, value) {
        try {
            if (value) {
                window.localStorage.setItem(key, value);
            } else {
                window.localStorage.removeItem(key);
            }
        } catch (error) {
            // Trình duyệt chặn lưu trữ thì chỉ mất phần ghi nhớ.
        }
    };

    document.querySelectorAll('[data-collapse-key]').forEach(function (group) {
        var key = group.dataset.collapseKey;
        var button = group.querySelector('.category-toggle');
        var apply = function (collapsed) {
            group.classList.toggle('is-collapsed', collapsed);
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        };

        apply(read(key) === '1');

        button.addEventListener('click', function () {
            var collapsed = !group.classList.contains('is-collapsed');
            apply(collapsed);
            write(key, collapsed ? '1' : '');
        });
    });

    // Tab đang chọn bị khuất bên phải (điện thoại): cuộn thanh tab cho thấy.
    document.querySelectorAll('.subnav').forEach(function (nav) {
        var active = nav.querySelector('.active');

        if (!active) {
            return;
        }

        var left = active.getBoundingClientRect().left - nav.getBoundingClientRect().left;

        if (left + active.offsetWidth > nav.clientWidth) {
            nav.scrollLeft = left - (nav.clientWidth - active.offsetWidth) / 2;
        }
    });

    // Menu 3 chấm: chỉ mở một cái, bấm ra ngoài hoặc Esc thì đóng.
    var kebabs = Array.prototype.slice.call(document.querySelectorAll('.kebab'));
    // Tìm lại mỗi lần vì có phần tự tải lại (màn hình khu) thay menu mới.
    var closeKebabs = function (except) {
        document.querySelectorAll('.kebab').forEach(function (kebab) {
            if (kebab !== except) {
                kebab.open = false;
            }
        });
    };

    document.addEventListener('click', function (event) {
        closeKebabs(event.target.closest ? event.target.closest('.kebab') : null);
    });

    // Trong khung cuộn ngang (bảng), menu nổi lên trên để không bị cắt.
    kebabs.forEach(function (kebab) {
        if (!kebab.closest('.table-wrap')) {
            return;
        }

        var menu = kebab.querySelector('.kebab-menu');

        kebab.addEventListener('toggle', function () {
            menu.classList.toggle('is-floating', kebab.open);

            if (!kebab.open) {
                return;
            }

            var box = kebab.querySelector('summary').getBoundingClientRect();
            var left = Math.min(box.left, window.innerWidth - menu.offsetWidth - 8);
            var top = box.bottom + 4 + menu.offsetHeight > window.innerHeight ? box.top - 4 - menu.offsetHeight : box.bottom + 4;

            menu.style.left = Math.max(8, left) + 'px';
            menu.style.top = Math.max(8, top) + 'px';
        });
    });

    window.addEventListener('scroll', function () {
        kebabs.forEach(function (kebab) {
            if (kebab.open && kebab.closest('.table-wrap')) {
                kebab.open = false;
            }
        });
    }, true);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeKebabs(null);
        }
    });

    // Xác nhận trước khi gửi: form có data-confirm (nội dung), data-confirm-title, data-confirm-ok,
    // data-confirm-danger. Nghe ở pha capture để chặn trước các xử lý submit khác.
    var confirmDialog = document.getElementById('confirm-dialog');
    var pendingConfirm = null;

    if (confirmDialog) {
        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form.matches || !form.matches('form[data-confirm]') || form.dataset.confirmed === '1') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            closeKebabs(null);
            pendingConfirm = { form: form, submitter: event.submitter || null };

            var ok = confirmDialog.querySelector('[data-confirm-ok]');

            var title = confirmDialog.querySelector('[data-confirm-title]');

            title.textContent = form.dataset.confirmTitle || title.dataset.default;
            confirmDialog.querySelector('[data-confirm-body]').textContent = form.dataset.confirm;
            ok.textContent = form.dataset.confirmOk || ok.dataset.defaultLabel || 'OK';
            ok.classList.toggle('btn-danger', 'confirmDanger' in form.dataset);
            confirmDialog.showModal();
        }, true);

        confirmDialog.querySelector('[data-confirm-ok]').addEventListener('click', function () {
            var target = pendingConfirm;

            confirmDialog.close();

            if (target) {
                pendingConfirm = null;
                target.form.dataset.confirmed = '1';
                target.form.requestSubmit(target.submitter);
            }
        });

        confirmDialog.addEventListener('close', function () {
            pendingConfirm = null;
        });
    }

    // Thông báo thành công tự tắt sau vài giây; lỗi và cảnh báo thì giữ lại.
    document.querySelectorAll('.flash[data-autohide]').forEach(function (flash) {
        setTimeout(function () {
            flash.classList.add('is-leaving');
            setTimeout(function () {
                flash.remove();
            }, 400);
        }, 4000);
    });

    // Quyền riêng của nhân viên: quyền đã có từ chức vụ đang chọn thì hiện bật sẵn, khoá lại.
    document.querySelectorAll('form[data-extra-perms]').forEach(function (form) {
        var extras = form.querySelectorAll('[data-extra-perm]');
        var counter = form.querySelector('[data-extra-count]');

        extras.forEach(function (input) {
            input.dataset.own = input.checked ? '1' : '0';
        });

        var sync = function () {
            var covered = {};
            var count = 0;

            form.querySelectorAll('[data-position-perms]').forEach(function (position) {
                if (position.checked) {
                    JSON.parse(position.dataset.positionPerms || '[]').forEach(function (key) {
                        covered[key] = true;
                    });
                }
            });

            extras.forEach(function (input) {
                var fromPosition = covered[input.value] === true;

                input.disabled = fromPosition;
                input.checked = fromPosition || input.dataset.own === '1';
                input.closest('.toggle-row').querySelector('[data-from-position]').hidden = !fromPosition;
                count += !fromPosition && input.checked ? 1 : 0;
            });

            if (counter) {
                counter.textContent = count;
                counter.hidden = count === 0;
            }
        };

        form.addEventListener('change', function (event) {
            if (event.target.matches('[data-extra-perm]')) {
                event.target.dataset.own = event.target.checked ? '1' : '0';
            }

            if (event.target.matches('[data-extra-perm], [data-position-perms]')) {
                sync();
            }
        });

        sync();
    });

    // Tuỳ chọn khi gọi món: thêm, xoá nhóm và lựa chọn ngay trên form, gửi một lần.
    document.querySelectorAll('form[data-option-editor]').forEach(function (form) {
        var list = form.querySelector('[data-option-groups]');
        var empty = form.querySelector('[data-options-empty]');
        var groupTemplate = form.querySelector('[data-group-template]').innerHTML;
        var optionTemplate = form.querySelector('[data-option-template]').innerHTML;
        var counter = 0;

        var key = function (prefix) {
            counter += 1;

            return prefix + Date.now().toString(36) + counter;
        };

        var fromHtml = function (html) {
            var holder = document.createElement('div');

            holder.innerHTML = html.trim();

            return holder.firstElementChild;
        };

        var changed = function () {
            empty.hidden = list.querySelector('[data-option-group]') !== null;
            form.dispatchEvent(new Event('change', { bubbles: true }));
        };

        form.addEventListener('click', function (event) {
            var button = event.target.closest('button');

            if (!button) {
                return;
            }

            if (button.matches('[data-add-group]')) {
                var group = fromHtml(groupTemplate.split('__G__').join(key('n')).split('__O__').join(key('p')));

                list.appendChild(group);
                group.querySelector('input').focus();
                changed();
            } else if (button.matches('[data-add-option]')) {
                var owner = button.closest('[data-option-group]');
                var row = fromHtml(optionTemplate.split('__G__').join(owner.dataset.key).split('__O__').join(key('p')));

                owner.querySelector('[data-option-rows]').appendChild(row);
                row.querySelector('input').focus();
                changed();
            } else if (button.matches('[data-remove-option]')) {
                var rows = button.closest('[data-option-rows]');

                // Nhóm luôn còn ít nhất một lựa chọn.
                if (rows.querySelectorAll('[data-option-row]').length > 1) {
                    button.closest('[data-option-row]').remove();
                    changed();
                }
            } else if (button.matches('[data-remove-group]')) {
                button.closest('[data-option-group]').remove();
                changed();
            }
        });
    });

    // Âm thanh báo món: trình duyệt chỉ cho phát sau khi người dùng bấm vào trang một lần.
    var sound = { ctx: null };
    var unlockSound = function () {
        var Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) {
            return null;
        }

        if (!sound.ctx) {
            sound.ctx = new Context();
        }

        return sound.ctx.state === 'suspended' ? sound.ctx.resume() : Promise.resolve();
    };
    var beep = function () {
        if (!sound.ctx || sound.ctx.state !== 'running') {
            return;
        }

        var start = sound.ctx.currentTime;

        [[880, 0], [1175, 0.18], [1568, 0.36]].forEach(function (note) {
            var oscillator = sound.ctx.createOscillator();
            var gain = sound.ctx.createGain();
            var at = start + note[1];

            oscillator.type = 'sine';
            oscillator.frequency.value = note[0];
            gain.gain.setValueAtTime(0.0001, at);
            gain.gain.exponentialRampToValueAtTime(0.35, at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.3);
            oscillator.connect(gain);
            gain.connect(sound.ctx.destination);
            oscillator.start(at);
            oscillator.stop(at + 0.32);
        });
    };
    ['pointerdown', 'keydown'].forEach(function (name) {
        document.addEventListener(name, function () {
            if (document.querySelector('[data-dine-alerts], [data-board]')) {
                unlockSound();
            }
        }, true);
    });
    var confirmOpen = function () {
        var dialog = document.getElementById('confirm-dialog');

        return !!(dialog && dialog.open);
    };

    var soundToggle = document.querySelector('[data-sound-toggle]');

    if (soundToggle) {
        var syncSound = function () {
            var on = !!(sound.ctx && sound.ctx.state === 'running');

            soundToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            soundToggle.textContent = on ? soundToggle.dataset.on : soundToggle.dataset.off;
        };

        soundToggle.addEventListener('click', function () {
            var waiting = unlockSound();

            if (waiting) {
                waiting.then(function () {
                    beep();
                    syncSound();
                });
            }
        });
        document.addEventListener('pointerdown', function () {
            window.setTimeout(syncSound, 200);
        });
        syncSound();
    }

    // Màn hình khu (bếp, bar): tải lại danh sách món mỗi 5 giây, có món mới thì kêu.
    var board = document.querySelector('[data-board]');

    if (board) {
        var lineIds = function (root) {
            return Array.prototype.map.call(root.querySelectorAll('[data-line-id]'), function (line) {
                return line.dataset.lineId;
            });
        };
        var known = lineIds(board);

        // Đang mở hộp thoại hoặc menu 3 chấm trên bảng thì chờ, để không mất chỗ đang thao tác.
        var busy = function () {
            return confirmOpen() || !!document.querySelector('dialog[open]') || !!board.querySelector('details.kebab[open]');
        };

        window.setInterval(function () {
            if (document.hidden || busy()) {
                return;
            }

            fetch(window.location.pathname, { headers: { 'X-Board': '1' }, credentials: 'same-origin' }).then(function (response) {
                if (response.redirected || !response.ok) {
                    if (response.redirected || response.status === 401 || response.status === 403) {
                        window.location.reload();
                    }

                    return null;
                }

                return response.text();
            }).then(function (html) {
                if (html === null || busy()) {
                    return;
                }

                var fresh = document.createElement('div');

                fresh.innerHTML = html;

                var ids = lineIds(fresh);
                var added = ids.filter(function (id) {
                    return known.indexOf(id) === -1;
                });

                added.forEach(function (id) {
                    var line = fresh.querySelector('[data-line-id="' + id + '"]');
                    var ticket = line ? line.closest('[data-ticket]') : null;

                    if (ticket) {
                        ticket.classList.add('is-new');
                    }
                });

                if (added.length > 0) {
                    beep();
                }

                known = ids;
                board.replaceChildren.apply(board, Array.prototype.slice.call(fresh.childNodes));
            }).catch(function () {});
        }, 5000);
    }

    // Báo món trên mọi trang: món đã xong (người phục vụ), món mới ở khu mình (bếp, bar).
    var alerts = document.querySelector('[data-dine-alerts]');

    if (alerts) {
        var alertsPanel = alerts.querySelector('[data-alerts-panel]');
        var alertsToggle = alerts.querySelector('[data-alerts-toggle]');
        var alertsBody = alerts.querySelector('[data-alerts-body]');
        var alertsCount = alerts.querySelector('[data-alerts-count]');
        var onStation = board ? board.dataset.boardStation : null;
        var readSeen = function () {
            try {
                return JSON.parse(window.sessionStorage.getItem('dinein-seen'));
            } catch (error) {
                return null;
            }
        };
        var seen = readSeen();
        var element = function (tag, className, text) {
            var node = document.createElement(tag);

            if (className) {
                node.className = className;
            }

            if (text) {
                node.textContent = text;
            }

            return node;
        };
        var setOpen = function (open) {
            alertsPanel.hidden = !open;
            alertsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        alertsToggle.addEventListener('click', function () {
            setOpen(alertsPanel.hidden);
        });
        alerts.querySelector('[data-alerts-close]').addEventListener('click', function () {
            setOpen(false);
        });

        var render = function (data) {
            var readyIds = data.ready.map(function (line) { return line.id; });
            var stations = data.stations.filter(function (station) {
                return String(station.id) !== onStation && station.ids.length > 0;
            });
            var newIds = [].concat.apply([], data.stations.map(function (station) { return station.ids; }));
            var fresh = !seen ? [] : readyIds.filter(function (id) {
                return seen.ready.indexOf(id) === -1;
            }).concat(stations.length === 0 ? [] : [].concat.apply([], stations.map(function (station) {
                return station.ids.filter(function (id) {
                    return seen.fresh.indexOf(id) === -1;
                });
            })));

            seen = { ready: readyIds, fresh: newIds };

            try {
                window.sessionStorage.setItem('dinein-seen', JSON.stringify(seen));
            } catch (error) {}

            if (fresh.length > 0) {
                beep();
                alerts.classList.remove('is-ringing');
                void alerts.offsetWidth;
                alerts.classList.add('is-ringing');
            }

            var count = readyIds.length + stations.reduce(function (sum, station) { return sum + station.ids.length; }, 0);

            alerts.hidden = count === 0;
            alertsCount.textContent = count;

            if (count === 0) {
                setOpen(false);
            }

            // Đang hỏi xác nhận "Đã mang ra" thì không vẽ lại, để form còn gửi được.
            if (confirmOpen()) {
                return;
            }

            alertsBody.replaceChildren();

            stations.forEach(function (station) {
                var row = element('p', 'dine-alert-row');

                row.appendChild(element('strong', '', alerts.dataset.newLabel.replace(':station', station.name) + ': ' + station.ids.length));
                var link = element('a', 'btn btn-sm', alerts.dataset.openLabel);
                link.href = station.url;
                row.appendChild(link);
                alertsBody.appendChild(row);
            });

            if (data.ready.length > 0) {
                alertsBody.appendChild(element('h3', '', alerts.dataset.readyLabel));
                var list = element('ul', 'rows');

                data.ready.forEach(function (line) {
                    var item = element('li');
                    var name = element('span', 'row-name');
                    var table = element('a', 'dine-alert-table', line.table);

                    table.href = line.table_url;
                    name.appendChild(table);
                    name.appendChild(document.createTextNode(' ' + line.text));

                    if (line.detail) {
                        name.appendChild(element('small', '', line.detail));
                    }

                    var form = element('form');
                    form.method = 'post';
                    form.action = line.serve_url;
                    form.dataset.confirm = alerts.dataset.serveConfirm.replace(':name', line.text);
                    form.dataset.confirmOk = alerts.dataset.serveLabel;

                    var token = element('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = alerts.dataset.csrf;
                    form.appendChild(token);

                    var button = element('button', 'btn btn-sm', alerts.dataset.serveLabel);
                    button.type = 'submit';
                    form.appendChild(button);

                    item.appendChild(name);
                    item.appendChild(form);
                    list.appendChild(item);
                });
                alertsBody.appendChild(list);
            }
        };

        var poll = function () {
            if (document.hidden) {
                return;
            }

            fetch(alerts.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (response) {
                return response.ok && !response.redirected ? response.json() : null;
            }).then(function (data) {
                if (data) {
                    render(data);
                }
            }).catch(function () {});
        };

        poll();
        window.setInterval(poll, 8000);
        document.addEventListener('visibilitychange', poll);
    }

    // Chọn món (Lấy đơn tại bàn, Đặt mang đi): bấm món mở hộp thoại tuỳ chọn, số lượng, ghi chú;
    // đơn giữ ở trình duyệt đến khi gửi. Món đã có của đơn mang đi (line) giữ giá lúc đặt nếu không đổi tuỳ chọn.
    var dineForm = document.querySelector('[data-dine-order]');

    if (dineForm) {
        var dineMenu = {};
        var cart = [];
        var dineMoney = function (amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
        };

        JSON.parse(dineForm.querySelector('[data-dine-menu]').textContent).forEach(function (item) {
            dineMenu[item.id] = item;
        });
        var keptNode = dineForm.querySelector('[data-dine-kept]');
        var kept = keptNode ? JSON.parse(keptNode.textContent) : {};
        var byNumber = function (a, b) { return a - b; };

        JSON.parse(dineForm.querySelector('[data-dine-old]').textContent).forEach(function (line) {
            if (!dineMenu[line.item] || !(Number(line.quantity) > 0)) {
                return;
            }

            var entry = {
                item: Number(line.item),
                quantity: Math.min(999, Math.max(1, Number(line.quantity) || 1)),
                options: (line.options || []).map(Number).sort(byNumber),
                note: line.note || '',
            };
            var original = line.line ? kept[line.line] : null;

            if (original && Number(original.item) === entry.item) {
                entry.line = original.line;
                entry.unit = original.unit;
                entry.name = original.name;
                entry.keptOptions = original.options.map(Number).sort(byNumber).join();
                entry.keptLines = original.optionLines;
            }

            cart.push(entry);
        });

        // Món cũ còn nguyên tuỳ chọn thì hiện và tính như trên đơn.
        var isKept = function (line) {
            return line.line !== undefined && line.options.join() === line.keptOptions;
        };

        var allOptions = function (item) {
            return [].concat.apply([], item.groups.map(function (group) { return group.options; }));
        };
        var unitPrice = function (line) {
            var item = dineMenu[line.item];

            if (isKept(line)) {
                return line.unit;
            }

            return allOptions(item).reduce(function (sum, option) {
                return sum + (line.options.indexOf(option.id) !== -1 ? option.price : 0);
            }, item.price);
        };
        // Tuỳ chọn theo nhóm, mỗi nhóm một dòng: "Thịt: Sườn cọng".
        var optionLines = function (line) {
            if (isKept(line)) {
                return line.keptLines;
            }

            return dineMenu[line.item].groups.map(function (group) {
                var names = group.options.filter(function (option) {
                    return line.options.indexOf(option.id) !== -1;
                }).map(function (option) { return option.name; });

                return names.length ? group.name + ': ' + names.join(', ') : '';
            }).filter(Boolean);
        };
        var hidden = function (name, value) {
            var input = document.createElement('input');

            input.type = 'hidden';
            input.name = name;
            input.value = value;

            return input;
        };
        var inputs = dineForm.querySelector('[data-dine-inputs]');
        var cartLists = document.querySelectorAll('[data-cart-lines]');
        var fixedCount = Number(dineForm.dataset.fixedCount) || 0;

        var renderCart = function () {
            var count = 0;
            var total = 0;
            var perItem = {};

            inputs.replaceChildren();
            cartLists.forEach(function (list) {
                list.replaceChildren();
            });

            cart.forEach(function (line, index) {
                var prefix = 'lines[' + index + ']';
                var price = unitPrice(line) * line.quantity;

                inputs.appendChild(hidden(prefix + '[item]', line.item));
                inputs.appendChild(hidden(prefix + '[quantity]', line.quantity));
                line.options.forEach(function (id) {
                    inputs.appendChild(hidden(prefix + '[options][]', id));
                });
                inputs.appendChild(hidden(prefix + '[note]', line.note));
                if (line.line !== undefined) {
                    inputs.appendChild(hidden(prefix + '[line]', line.line));
                }

                count += line.quantity;
                total += price;
                perItem[line.item] = (perItem[line.item] || 0) + line.quantity;

                var row = document.createElement('li');
                var name = document.createElement('span');
                name.className = 'row-name';
                name.textContent = line.quantity + ' × ' + (isKept(line) && line.name ? line.name : dineMenu[line.item].name);

                optionLines(line).concat(line.note ? [line.note] : []).forEach(function (text, position, list) {
                    var small = document.createElement('small');
                    small.textContent = text;
                    small.className = line.note && position === list.length - 1 ? 'dine-note' : '';
                    name.appendChild(small);
                });

                var amount = document.createElement('strong');
                amount.className = 'line-total';
                amount.textContent = dineMoney(price);

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'row-btn';
                remove.dataset.removeLine = index;
                remove.setAttribute('aria-label', dineForm.dataset.removeLabel);
                remove.textContent = '✕';

                var edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'row-btn';
                edit.dataset.editLine = index;
                edit.setAttribute('aria-label', dineForm.dataset.editLabel);
                edit.title = dineForm.dataset.editLabel;
                edit.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
                remove.title = dineForm.dataset.removeLabel;

                var tools = document.createElement('span');
                tools.className = 'line-tools';
                tools.appendChild(edit);
                tools.appendChild(remove);

                row.appendChild(name);
                row.appendChild(amount);
                row.appendChild(tools);
                cartLists.forEach(function (list, position) {
                    list.appendChild(position === 0 ? row : row.cloneNode(true));
                });
            });

            document.querySelectorAll('[data-dine-count]').forEach(function (node) {
                node.textContent = dineForm.dataset.countLabel.replace(':count', count);
            });
            document.querySelectorAll('[data-dine-total]').forEach(function (node) {
                node.textContent = dineMoney(total);
            });
            document.querySelectorAll('[data-dine-submit], [data-cart-send], [data-dine-review]').forEach(function (button) {
                button.disabled = cart.length === 0;
            });
            document.querySelectorAll('[data-cart-empty]').forEach(function (node) {
                node.hidden = cart.length > 0 || fixedCount > 0;
            });
            document.querySelectorAll('[data-dine-pick]').forEach(function (button) {
                var badge = button.querySelector('[data-picked-count]');
                var picked = perItem[button.dataset.dinePick] || 0;

                badge.hidden = picked === 0;
                badge.textContent = '× ' + picked;
            });

            // Đơn mang đi tính giảm giá và tổng tiền từ đây.
            dineForm.cartState = { count: count, total: total };
            dineForm.dispatchEvent(new CustomEvent('cart-change'));
        };

        var onCartClick = function (event) {
            var remove = event.target.closest('[data-remove-line]');

            if (remove) {
                cart.splice(Number(remove.dataset.removeLine), 1);
                renderCart();
            }

            var edit = event.target.closest('[data-edit-line]');

            if (edit) {
                openItem(cart[Number(edit.dataset.editLine)].item, Number(edit.dataset.editLine));
            }
        };

        cartLists.forEach(function (list) {
            list.addEventListener('click', onCartClick);
        });
        // Hộp chọn món (đơn mang đi): mỗi lần mở là tìm lại từ đầu.
        var pickDialog = document.getElementById('pick-dialog');
        var pickSearch = pickDialog ? pickDialog.querySelector('[data-search-input]') : null;
        if (pickSearch) {
            pickDialog.addEventListener('close', function () {
                pickSearch.value = '';
                pickSearch.dispatchEvent(new Event('input'));
            });
        }

        var cartSend = document.querySelector('[data-cart-send]');
        if (cartSend) {
            cartSend.addEventListener('click', function () {
                dineForm.requestSubmit();
            });
        }
        dineForm.addEventListener('submit', function () {
            dineForm.querySelectorAll('[data-dine-submit]').forEach(function (button) {
                button.disabled = true;
            });
        });

        var itemDialog = document.getElementById('dine-item');
        var itemForm = itemDialog.querySelector('[data-dine-item-form]');
        var groupsBox = itemForm.querySelector('[data-item-groups]');
        var quantityInput = itemForm.querySelector('input[name=item_quantity]');
        var noteInput = itemForm.querySelector('[data-item-note]');
        var itemError = itemForm.querySelector('[data-item-error]');
        var addButton = itemForm.querySelector('[data-item-add]');
        var optionsBox = itemForm.querySelector('[data-item-options]');
        var pickedText = itemForm.querySelector('[data-item-picked]');
        var currentItem = null;
        var editing = null; // vị trí món đang sửa trong đơn, null khi thêm mới

        var picked = function () {
            return Array.prototype.map.call(groupsBox.querySelectorAll('input:checked'), function (input) {
                return Number(input.value);
            });
        };
        var quantity = function () {
            return Math.min(99, Math.max(1, parseInt(quantityInput.value, 10) || 1));
        };
        var syncAdd = function () {
            itemError.hidden = true;
            var draft = { item: currentItem.id, options: picked().sort(byNumber) };
            var edited = editing === null ? null : cart[editing];

            if (edited && edited.line !== undefined) {
                draft.line = edited.line;
                draft.unit = edited.unit;
                draft.keptOptions = edited.keptOptions;
            }

            // Tóm tắt tuỳ chọn đã chọn ở dòng Tuỳ chọn, để thấy cả khi đang đóng.
            var chosen = optionLines({ item: currentItem.id, options: draft.options });
            pickedText.textContent = chosen.length ? chosen.join('; ') : dineForm.dataset.optionsNone;

            addButton.textContent = (editing === null ? dineForm.dataset.addLabel : dineForm.dataset.updateLabel) + ' · ' + dineMoney(unitPrice(draft) * quantity());
        };

        var openItem = function (id, lineIndex) {
            var line = lineIndex === undefined ? null : cart[lineIndex];

            editing = line ? lineIndex : null;
            currentItem = dineMenu[id];
            itemForm.querySelector('[data-item-name]').textContent = currentItem.name;
            itemForm.querySelector('[data-item-price]').textContent = dineMoney(currentItem.price);
            groupsBox.replaceChildren();

            currentItem.groups.forEach(function (group) {
                var fieldset = document.createElement('fieldset');
                var legend = document.createElement('legend');
                var hint = document.createElement('small');
                var choices = document.createElement('div');

                fieldset.className = 'option-pick';
                legend.textContent = group.name + ' ';
                hint.textContent = (group.multiple ? dineForm.dataset.pickMany : dineForm.dataset.pickOne) + (group.required ? ' · ' + dineForm.dataset.requiredLabel : '');
                legend.appendChild(hint);
                choices.className = 'chips';
                fieldset.dataset.group = group.name;
                fieldset.dataset.required = group.required ? '1' : '';

                group.options.forEach(function (option) {
                    var label = document.createElement('label');
                    var input = document.createElement('input');
                    var text = document.createElement('span');

                    label.className = 'chip';
                    input.type = group.multiple ? 'checkbox' : 'radio';
                    input.name = 'group-' + group.id;
                    input.value = option.id;
                    input.checked = line !== null && line.options.indexOf(option.id) !== -1;
                    text.textContent = option.name + (option.price > 0 ? ' +' + dineMoney(option.price) : '');
                    label.appendChild(input);
                    label.appendChild(text);
                    choices.appendChild(label);
                });

                fieldset.appendChild(legend);
                fieldset.appendChild(choices);
                groupsBox.appendChild(fieldset);
            });

            // Món có nhóm bắt buộc thì mở sẵn Tuỳ chọn khi thêm mới; còn lại đóng để không phải cuộn.
            optionsBox.hidden = currentItem.groups.length === 0;
            optionsBox.open = !line && currentItem.groups.some(function (group) { return group.required; });
            itemForm.querySelector('.item-body').scrollTop = 0;

            quantityInput.value = line ? line.quantity : 1;
            noteInput.value = line ? line.note : '';
            itemError.hidden = true;
            syncAdd();
            itemDialog.showModal();
        };

        document.addEventListener('click', function (event) {
            var pick = event.target.closest('[data-dine-pick]');

            if (pick) {
                openItem(pick.dataset.dinePick);
            }
        });

        // Bấm lại lựa chọn đang chọn (chọn một) thì bỏ chọn, cho nhóm không bắt buộc.
        groupsBox.addEventListener('pointerdown', function (event) {
            var label = event.target.closest('.chip');
            var input = label ? label.querySelector('input[type=radio]') : null;

            if (input) {
                input.dataset.wasChecked = input.checked ? '1' : '';
            }
        });
        groupsBox.addEventListener('click', function (event) {
            var input = event.target.closest('input[type=radio]');

            if (input && input.dataset.wasChecked === '1') {
                input.checked = false;
                input.dataset.wasChecked = '';
                syncAdd();
            }
        });
        itemDialog.addEventListener('close', function () {
            editing = null;
        });
        itemForm.addEventListener('change', syncAdd);
        itemForm.addEventListener('input', syncAdd);

        itemForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var missing = Array.prototype.find.call(groupsBox.querySelectorAll('fieldset'), function (fieldset) {
                return fieldset.dataset.required && !fieldset.querySelector('input:checked');
            });

            if (missing) {
                itemError.textContent = dineForm.dataset.needOption.replace(':group', missing.dataset.group);
                itemError.hidden = false;
                optionsBox.open = true;
                missing.scrollIntoView({ block: 'nearest' });

                return;
            }

            var line = { item: currentItem.id, quantity: quantity(), options: picked().sort(byNumber), note: noteInput.value.trim() };
            var edited = editing === null ? null : cart[editing];

            // Sửa món cũ của đơn: giữ id dòng để server giữ giá lúc đặt nếu tuỳ chọn không đổi.
            if (edited && edited.line !== undefined) {
                ['line', 'unit', 'name', 'keptOptions', 'keptLines'].forEach(function (key) {
                    line[key] = edited[key];
                });
            }
            var same = cart.find(function (other) {
                return other !== edited && other.line === line.line && other.item === line.item && other.note === line.note && other.options.join() === line.options.join();
            });

            // Sửa thành giống hệt một món khác trong đơn thì gộp số lượng.
            if (edited) {
                cart.splice(editing, 1);
            }

            if (same) {
                same.quantity = Math.min(999, same.quantity + line.quantity);
            } else if (edited) {
                cart.splice(editing, 0, line);
            } else {
                cart.push(line);
            }

            editing = null;

            renderCart();
            itemDialog.close();
        });

        renderCart();
    }

    // Chuẩn bị đơn mang đi: Xem đơn mở popup chi tiết (bảng tự tải lại nên bắt sự kiện ở document).
    var orderDialog = document.getElementById('prep-order');
    if (orderDialog) {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-show-order]');
            var template = button ? document.querySelector('template[data-order-detail="' + button.dataset.showOrder + '"]') : null;

            if (!template) {
                return;
            }

            closeKebabs(null);
            orderDialog.querySelector('[data-order-title]').textContent = template.dataset.title;
            orderDialog.querySelector('[data-order-body]').replaceChildren(template.content.cloneNode(true));
            orderDialog.showModal();
        });
    }

    // Hộp thoại có data-backdrop-close: bấm ra ngoài khung thì đóng (bắt ở document vì có hộp được tải lại).
    document.addEventListener('click', function (event) {
        var dialog = event.target;

        if (!(dialog instanceof HTMLDialogElement) || !dialog.hasAttribute('data-backdrop-close') || !dialog.open) {
            return;
        }

        var box = dialog.getBoundingClientRect();
        var inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;

        if (!inside) {
            dialog.close();
        }
    });

    // Ẩn hiện mật khẩu.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-password-toggle]');

        if (!button) {
            return;
        }

        var input = document.getElementById(button.getAttribute('aria-controls'));
        var show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        button.setAttribute('aria-label', show ? button.dataset.labelHide : button.dataset.labelShow);
    });

    // Hộp thoại.
    // Bắt ở document vì có phần tự tải lại (màn hình khu vực) thay nút mới.
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-open-dialog]');
        var closer = event.target.closest('[data-close-dialog]');

        if (opener) {
            closeKebabs(null);
            document.getElementById(opener.dataset.openDialog).showModal();
        } else if (closer && closer.closest('dialog')) {
            closer.closest('dialog').close();
        }
    });

    // Khách đặt trước đến: hộp thoại chung hỏi lại và cho chọn bàn; bàn của khu đã đặt lên đầu.
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-seat]');
        var dialog = document.getElementById('seat-dialog');

        if (!opener || !dialog) {
            return;
        }

        var form = dialog.querySelector('[data-seat-form]');
        var list = dialog.querySelector('.multi-options');
        var none = list.querySelector('input[value=""]');
        var area = opener.dataset.seatArea;

        closeKebabs(null);
        form.action = opener.dataset.seat;
        dialog.querySelector('[data-seat-summary]').textContent = opener.dataset.seatText;
        Array.prototype.slice.call(list.querySelectorAll('[data-pick-option]')).sort(function (a, b) {
            var rank = function (item) {
                var value = item.querySelector('input').value;

                return value === '' ? 0 : (area && new RegExp('^' + area + '[0-9]+$').test(value) ? 1 : 2);
            };

            return rank(a) - rank(b) || Number(a.dataset.order) - Number(b.dataset.order);
        }).forEach(function (item) { list.appendChild(item); });
        none.checked = true;
        dialog.querySelector('[data-pick-value]').textContent = none.dataset.label;
        dialog.showModal();
    });

    // Khung tải ảnh: xem trước, kéo thả, xoá.
    document.querySelectorAll('[data-image-upload]').forEach(function (box) {
        var field = box.parentElement;
        var input = box.querySelector('input[type=file]');
        var preview = box.querySelector('[data-preview]');
        var removeButton = field.querySelector('[data-remove]');
        var removeFlag = field.querySelector('[data-remove-flag]');
        var show = function (url) {
            preview.src = url || '';
            preview.hidden = !url;
            box.classList.toggle('has-image', Boolean(url));
            box.querySelector('[data-empty]').hidden = Boolean(url);
            box.querySelector('[data-change]').hidden = !url;

            if (removeButton) {
                removeButton.hidden = !url;
            }
        };

        input.addEventListener('change', function () {
            if (input.files[0]) {
                show(URL.createObjectURL(input.files[0]));

                if (removeFlag) {
                    removeFlag.value = '0';
                }
            }
        });

        ['dragenter', 'dragover'].forEach(function (name) {
            input.addEventListener(name, function () {
                box.classList.add('is-dragging');
            });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            input.addEventListener(name, function () {
                box.classList.remove('is-dragging');
            });
        });

        if (removeButton) {
            removeButton.addEventListener('click', function () {
                input.value = '';
                show(null);
                removeFlag.value = '1';
            });
        }
    });

    // Chọn "Danh mục mới" trong form món.
    var categorySelect = document.getElementById('f-menu_category_id');
    var newCategory = document.querySelector('[data-new-category]');

    if (categorySelect && newCategory) {
        var newCategoryInput = newCategory.querySelector('input');
        var syncCategory = function (focus) {
            var isNew = categorySelect.value === 'new';
            newCategory.hidden = !isNew;
            newCategoryInput.required = isNew;

            if (isNew && focus) {
                newCategoryInput.focus();
            }
        };

        categorySelect.addEventListener('change', function () {
            syncCategory(true);
        });
        syncCategory(false);
    }

    // Kéo thả đổi thứ tự: nút kéo dùng được cả chuột và cảm ứng, giữ lâu vào dòng
    // chỉ dành cho cảm ứng. Mũi tên lên xuống trên nút kéo cho người dùng bàn phím.
    var LONG_PRESS_MS = 350;
    var LONG_PRESS_SLOP = 10;
    var EDGE = 80;
    var drag = null;

    var childrenOf = function (list) {
        return Array.prototype.filter.call(list.children, function (child) {
            return child.hasAttribute('data-sort-id') && !child.hidden;
        });
    };

    var orderOf = function (list) {
        return childrenOf(list).map(function (child) {
            return child.dataset.sortId;
        });
    };

    var save = function (list) {
        fetch(list.dataset.sortUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ ids: orderOf(list).map(Number) }),
        }).then(function (response) {
            if (!response.ok) {
                window.location.reload();
            }
        }).catch(function () {
            window.location.reload();
        });
    };

    var placeDragged = function () {
        drag.item.style.transform = '';
        var top = drag.item.getBoundingClientRect().top;
        drag.item.style.transform = 'translateY(' + (drag.y - drag.grab - top) + 'px)';
    };

    var moveDragged = function () {
        var before = null;

        childrenOf(drag.list).some(function (child) {
            if (child === drag.item) {
                return false;
            }

            var rect = child.getBoundingClientRect();

            if (drag.y < rect.top + rect.height / 2) {
                before = child;

                return true;
            }

            return false;
        });

        if (before !== drag.item.nextElementSibling) {
            drag.list.insertBefore(drag.item, before);
        }

        placeDragged();
    };

    var scrollTick = function () {
        if (!drag) {
            return;
        }

        var speed = 0;

        if (drag.y < EDGE) {
            speed = -10;
        } else if (drag.y > window.innerHeight - EDGE) {
            speed = 10;
        }

        if (speed !== 0) {
            window.scrollBy(0, speed);
            moveDragged();
        }

        window.requestAnimationFrame(scrollTick);
    };

    var startDrag = function (list, item, y) {
        var rect;

        list.classList.add('is-sorting');
        rect = item.getBoundingClientRect();

        drag = {
            list: list,
            item: item,
            y: y,
            grab: Math.min(Math.max(y - rect.top, 0), rect.height),
            before: orderOf(list).join(),
        };

        item.classList.add('is-dragging');
        document.body.classList.add('drag-active');
        closeKebabs(null);
        placeDragged();
        window.requestAnimationFrame(scrollTick);
    };

    var endDrag = function () {
        var finished = drag;

        drag = null;
        finished.item.classList.remove('is-dragging');
        finished.item.style.transform = '';
        finished.list.classList.remove('is-sorting');
        document.body.classList.remove('drag-active');

        if (orderOf(finished.list).join() !== finished.before) {
            save(finished.list);
        }

        // Thả tay xong trình duyệt có thể phát sinh một cú bấm vào link của dòng.
        var swallow = function (event) {
            event.preventDefault();
            event.stopPropagation();
        };

        document.addEventListener('click', swallow, true);
        window.setTimeout(function () {
            document.removeEventListener('click', swallow, true);
        }, 350);
    };

    document.addEventListener('pointermove', function (event) {
        if (drag) {
            drag.y = event.clientY;
            moveDragged();
        }
    });
    document.addEventListener('pointerup', function () {
        if (drag) {
            endDrag();
        }
    });
    document.addEventListener('pointercancel', function () {
        if (drag) {
            endDrag();
        }
    });
    // Đang kéo thì ngón tay không được cuộn trang.
    document.addEventListener('touchmove', function (event) {
        if (drag) {
            event.preventDefault();
        }
    }, { passive: false });

    document.querySelectorAll('[data-sortable]').forEach(function (list) {
        list.addEventListener('pointerdown', function (event) {
            var item = event.target.closest('[data-sort-id]');

            if (!item || item.parentElement !== list || drag || document.querySelector('.is-searching')) {
                return;
            }

            if (event.target.closest('.drag-handle')) {
                if (event.pointerType === 'mouse' && event.button !== 0) {
                    return;
                }

                event.preventDefault();
                event.target.closest('.drag-handle').setPointerCapture(event.pointerId);
                startDrag(list, item, event.clientY);

                return;
            }

            var pressArea = list.dataset.sortPress;

            if (event.pointerType !== 'touch' || event.target.closest('.kebab, dialog') || (pressArea && !event.target.closest(pressArea))) {
                return;
            }

            var startX = event.clientX;
            var startY = event.clientY;
            var timer;
            var stop = function () {
                window.clearTimeout(timer);
                item.classList.remove('is-pressing');
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', stop);
                document.removeEventListener('pointercancel', stop);
                document.removeEventListener('contextmenu', blockMenu);
            };
            var onMove = function (moveEvent) {
                if (Math.abs(moveEvent.clientX - startX) > LONG_PRESS_SLOP || Math.abs(moveEvent.clientY - startY) > LONG_PRESS_SLOP) {
                    stop();
                }
            };
            var blockMenu = function (menuEvent) {
                menuEvent.preventDefault();
            };

            item.classList.add('is-pressing');
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', stop);
            document.addEventListener('pointercancel', stop);
            document.addEventListener('contextmenu', blockMenu);

            timer = window.setTimeout(function () {
                stop();
                document.addEventListener('contextmenu', blockMenu, { once: true });

                if (navigator.vibrate) {
                    navigator.vibrate(15);
                }

                startDrag(list, item, startY);
            }, LONG_PRESS_MS);
        });

        list.addEventListener('keydown', function (event) {
            var handle = event.target.closest && event.target.closest('.drag-handle');

            if (!handle || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) {
                return;
            }

            var item = handle.closest('[data-sort-id]');

            if (item.parentElement !== list) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var siblings = childrenOf(list);
            var index = siblings.indexOf(item);
            var target = siblings[index + (event.key === 'ArrowUp' ? -1 : 1)];

            if (target) {
                list.insertBefore(item, event.key === 'ArrowUp' ? target : target.nextElementSibling);
                handle.focus();
                save(list);
            }
        });
    });

    // Có thay đổi chưa lưu thì hỏi trước khi rời trang.
    var guarded = Array.prototype.slice.call(document.querySelectorAll('form[data-guard]'));

    if (guarded.length) {
        var snapshot = function (form) {
            var parts = [];

            new FormData(form).forEach(function (value, name) {
                if (name !== '_token') {
                    parts.push(name + '=' + (typeof value === 'string' ? value : value.name + ':' + value.size));
                }
            });

            return parts.join('&');
        };
        var initial = guarded.map(snapshot);
        var leaving = false;
        var hasChanges = function () {
            return !leaving && guarded.some(function (form, index) {
                // Form vừa bị trả về vì lỗi nhập liệu: dữ liệu trên màn hình chưa được lưu.
                return form.querySelector('.error') !== null || snapshot(form) !== initial[index];
            });
        };
        var unsaved = document.getElementById('unsaved-dialog');
        var target = null;

        document.addEventListener('submit', function (event) {
            if (!event.defaultPrevented) {
                leaving = true;
            }
        });

        window.addEventListener('beforeunload', function (event) {
            if (hasChanges()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        // Bộ nhớ đệm khi bấm Back trình duyệt có thể đưa trang về đúng trạng thái cũ.
        window.addEventListener('pageshow', function () {
            leaving = false;
        });

        document.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;

            if (!link || !unsaved || !hasChanges() || event.ctrlKey || event.metaKey || event.shiftKey || link.target === '_blank') {
                return;
            }

            if (!/^https?:/.test(link.href) || link.getAttribute('href').charAt(0) === '#') {
                return;
            }

            event.preventDefault();
            target = link.href;
            unsaved.showModal();
        });

        if (unsaved) {
            unsaved.querySelector('[data-leave-page]').addEventListener('click', function () {
                leaving = true;
                window.location.href = target;
            });
        }
    }

    // Tạo đơn mang đi: chọn món dùng chung với gọi món tại bàn (ở trên); đây tính giảm giá và tổng.
    var orderForm = document.querySelector('[data-order-form]');

    if (orderForm) {
        var money = function (amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
        };

        var renderOrder = function () {
            var state = orderForm.cartState || { count: 0, total: 0 };
            var total = (Number(orderForm.dataset.fixedTotal) || 0) + state.total;
            var count = (Number(orderForm.dataset.fixedCount) || 0) + state.count;
            var off = discountOff(total);

            document.querySelectorAll('[data-order-saving]').forEach(function (saving) {
                saving.hidden = off === 0;
                saving.textContent = off > 0 ? '−' + money(off) : '';
            });
            document.querySelectorAll('[data-order-total]').forEach(function (node) {
                node.textContent = money(total - off);
            });
            document.querySelectorAll('[data-order-count]').forEach(function (node) {
                node.textContent = orderForm.dataset.countLabel.replace(':count', count);
            });
        };

        orderForm.addEventListener('cart-change', renderOrder);

        var discountSection = orderForm.querySelector('[data-discount]');
        var modeInputs = Array.prototype.slice.call(discountSection.querySelectorAll('input[name=discount_mode]'));
        var percentInput = orderForm.querySelector('[name=discount_percent]');
        var amountInput = orderForm.querySelector('[name=discount_thousands]');
        var discountToggle = orderForm.querySelector('[data-discount-toggle]');
        var codeInput = orderForm.querySelector('[data-code-input]');
        var codeMessage = orderForm.querySelector('[data-code-message]');
        var appliedCode = null;

        // Không có quyền giảm trực tiếp thì chỉ có một input ẩn mang giá trị code.
        var currentMode = function () {
            var checked = modeInputs.filter(function (input) {
                return input.checked || input.type === 'hidden';
            })[0];

            return checked.value;
        };

        // Cùng cách tính với DiscountType::amountOff ở server.
        function discountOff(subtotal) {
            var mode = discountToggle.checked ? currentMode() : 'none';
            var off = 0;

            if (mode === 'percent') {
                off = percentInput ? Math.round(subtotal * Math.min(100, Number(percentInput.value) || 0) / 100) : 0;
            } else if (mode === 'amount') {
                off = amountInput ? (Number(amountInput.value) || 0) * 1000 : 0;
            } else if (mode === 'code' && appliedCode) {
                off = appliedCode.type === 'percent' ? Math.round(subtotal * appliedCode.value / 100) : appliedCode.value;
            }

            return Math.max(0, Math.min(off, subtotal));
        }

        var checkCode = function () {
            var code = codeInput.value.trim();

            appliedCode = null;
            codeMessage.textContent = '';
            codeMessage.classList.remove('is-error');

            if (!code) {
                renderOrder();

                return;
            }

            // Mã đang có trên đơn được giữ nguyên, kể cả khi đã tắt.
            if (code.toUpperCase() === orderForm.dataset.keptCode) {
                appliedCode = { type: orderForm.dataset.keptType, value: Number(orderForm.dataset.keptValue) };
                codeMessage.textContent = orderForm.dataset.keptLabel;
                renderOrder();

                return;
            }

            fetch(orderForm.dataset.checkUrl + '?code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' } })
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {
                    appliedCode = result.valid ? result : null;
                    codeMessage.textContent = result.valid ? result.label : result.message;
                    codeMessage.classList.toggle('is-error', !result.valid);
                    renderOrder();
                });
        };

        var syncDiscountMode = function () {
            var mode = currentMode();

            discountSection.querySelector('[data-discount-body]').hidden = !discountToggle.checked;

            discountSection.querySelectorAll('[data-discount-field]').forEach(function (field) {
                field.hidden = field.dataset.discountField !== mode;
            });
            renderOrder();
        };

        modeInputs.forEach(function (input) {
            input.addEventListener('change', syncDiscountMode);
        });
        discountToggle.addEventListener('change', syncDiscountMode);

        [percentInput, amountInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', renderOrder);
            }
        });
        orderForm.querySelector('[data-code-apply]').addEventListener('click', checkCode);
        codeInput.addEventListener('input', function () {
            appliedCode = null;
            codeMessage.textContent = '';
            renderOrder();
        });
        codeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                checkCode();
            }
        });

        syncDiscountMode();

        if (discountToggle.checked && currentMode() === 'code' && codeInput.value) {
            checkCode();
        }
    }

    // Chọn ngày bằng lịch.
    var pad = function (number) {
        return (number < 10 ? '0' : '') + number;
    };
    var toIso = function (date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    };
    // Nhãn ngày có hai bản: đầy đủ cho màn hình rộng, gọn (T6 06/11) cho điện thoại.
    var setDateText = function (element, full, short) {
        var fullSpan = document.createElement('span');
        var shortSpan = document.createElement('span');

        fullSpan.className = 'date-full';
        fullSpan.textContent = full;
        shortSpan.className = 'date-short';
        shortSpan.textContent = short;
        element.textContent = '';
        element.appendChild(fullSpan);
        element.appendChild(shortSpan);
    };
    var shortDay = function (picker, iso, todayIso) {
        var date = fromIso(iso);
        var dayMonth = pad(date.getDate()) + '/' + pad(date.getMonth() + 1);

        return iso === todayIso
            ? picker.dataset.todayLabel + ' ' + dayMonth
            : JSON.parse(picker.dataset.weekdaysShort)[(date.getDay() + 6) % 7] + ' ' + dayMonth;
    };
    var fromIso = function (iso) {
        var parts = iso.split('-').map(Number);

        return new Date(parts[0], parts[1] - 1, parts[2]);
    };

    document.querySelectorAll('[data-date-picker]').forEach(function (picker) {
        var value = picker.querySelector('[data-date-value]');
        var text = picker.querySelector('[data-date-text]');
        var dialog = picker.querySelector('[data-date-dialog]');
        var days = picker.querySelector('[data-month-days]');
        var title = picker.querySelector('[data-month-title]');
        var weekdays = JSON.parse(picker.dataset.weekdays);
        var today = fromIso(picker.dataset.today);
        var min = picker.dataset.min;
        var withYear = picker.hasAttribute('data-with-year');
        // Ô được để trống (data-placeholder) thì lịch mở ở tháng hiện tại.
        var shown = value.value ? fromIso(value.value) : today;

        var label = function (iso) {
            var date = fromIso(iso);
            var diff = Math.round((date - today) / 86400000);
            var name = weekdays[(date.getDay() + 6) % 7] + ' ' + pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + (withYear ? '/' + date.getFullYear() : '');

            if (withYear) {
                return name;
            }

            if (diff === 0) {
                return picker.dataset.todayLabel + ', ' + name;
            }

            return diff === 1 ? picker.dataset.tomorrowLabel + ', ' + name : name;
        };

        var renderMonth = function () {
            var first = new Date(shown.getFullYear(), shown.getMonth(), 1);
            var count = new Date(shown.getFullYear(), shown.getMonth() + 1, 0).getDate();
            var blanks = (first.getDay() + 6) % 7;

            title.textContent = picker.dataset.monthLabel
                .replace(':month', shown.getMonth() + 1)
                .replace(':year', shown.getFullYear());
            picker.querySelector('[data-month-step="-1"]').disabled = toIso(first).slice(0, 7) <= min.slice(0, 7);

            if (withYear) {
                picker.querySelector('[data-month-step="-12"]').disabled = Boolean(min) && toIso(new Date(first.getFullYear() - 1, first.getMonth(), 1)).slice(0, 7) < min.slice(0, 7);
            }

            days.textContent = '';

            for (var blank = 0; blank < blanks; blank++) {
                days.appendChild(document.createElement('span'));
            }

            for (var day = 1; day <= count; day++) {
                var iso = toIso(new Date(shown.getFullYear(), shown.getMonth(), day));
                var button = document.createElement('button');

                button.type = 'button';
                button.textContent = day;
                button.dataset.date = iso;
                button.disabled = iso < min;
                button.classList.toggle('is-today', iso === picker.dataset.today);
                button.classList.toggle('is-selected', iso === value.value);
                button.setAttribute('aria-label', label(iso));
                days.appendChild(button);
            }
        };

        var choose = function (iso) {
            value.value = iso;
            text.textContent = label(iso);
            shown = fromIso(iso);
            setDateText(text, label(iso), withYear ? label(iso) : shortDay(picker, iso, picker.dataset.today));
            value.dispatchEvent(new Event('change', { bubbles: true }));
            dialog.close();
        };

        picker.querySelector('[data-date-trigger]').addEventListener('click', function () {
            shown = value.value ? fromIso(value.value) : today;
            renderMonth();
            dialog.showModal();
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();

                return;
            }

            var step = event.target.closest('[data-month-step]');
            var day = event.target.closest('[data-date]');
            var shortcut = event.target.closest('[data-date-shortcut]');

            if (step) {
                shown = new Date(shown.getFullYear(), shown.getMonth() + Number(step.dataset.monthStep), 1);
                renderMonth();
            } else if (day) {
                choose(day.dataset.date);
            } else if (shortcut) {
                choose(toIso(new Date(today.getFullYear(), today.getMonth(), today.getDate() + Number(shortcut.dataset.dateShortcut))));
            } else if (event.target.closest('[data-date-clear]')) {
                value.value = '';
                text.textContent = picker.dataset.placeholder;
                value.dispatchEvent(new Event('change', { bubbles: true }));
                dialog.close();
            }
        });

        // Cho đoạn code khác đổi ngày mà không mở lịch (ví dụ ngày kết thúc theo ngày bắt đầu).
        picker.setDate = function (iso) {
            value.value = iso;
            setDateText(text, label(iso), withYear ? label(iso) : shortDay(picker, iso, picker.dataset.today));
        };

        if (value.value) {
            setDateText(text, label(value.value), withYear ? label(value.value) : shortDay(picker, value.value, picker.dataset.today));
        } else {
            text.textContent = picker.dataset.placeholder;
        }
    });

    // Chọn khoảng ngày: bấm ngày đầu rồi ngày cuối, không dài quá data-max-days.
    document.querySelectorAll('[data-date-range]').forEach(function (picker) {
        var fromInput = picker.querySelector('[data-range-from]');
        var toInput = picker.querySelector('[data-range-to]');
        var text = picker.querySelector('[data-date-text]');
        var dialog = picker.querySelector('[data-date-dialog]');
        var days = picker.querySelector('[data-month-days]');
        var title = picker.querySelector('[data-month-title]');
        var status = picker.querySelector('[data-range-status]');
        var weekdays = JSON.parse(picker.dataset.weekdays);
        var todayIso = picker.dataset.today;
        var max = picker.dataset.max;
        var maxDays = Number(picker.dataset.maxDays);
        var shown = toInput.value ? fromIso(toInput.value) : new Date();
        var start = null;

        var addDays = function (iso, count) {
            var date = fromIso(iso);

            return toIso(new Date(date.getFullYear(), date.getMonth(), date.getDate() + count));
        };
        var short = function (iso) {
            var date = fromIso(iso);

            return pad(date.getDate()) + '/' + pad(date.getMonth() + 1);
        };
        var label = function (from, to) {
            if (from !== to) {
                return short(from) + ' – ' + short(to);
            }

            var name = weekdays[(fromIso(from).getDay() + 6) % 7] + ' ' + short(from);

            return from === todayIso ? picker.dataset.todayLabel + ', ' + name : name;
        };

        var renderMonth = function () {
            var first = new Date(shown.getFullYear(), shown.getMonth(), 1);
            var count = new Date(shown.getFullYear(), shown.getMonth() + 1, 0).getDate();
            var low = start ? start : fromInput.value;
            var high = start ? start : toInput.value;

            title.textContent = picker.dataset.monthLabel.replace(':month', shown.getMonth() + 1).replace(':year', shown.getFullYear());
            picker.querySelector('[data-month-step="1"]').disabled = Boolean(max) && toIso(first).slice(0, 7) >= max.slice(0, 7);
            status.textContent = start ? picker.dataset.endHint : picker.dataset.startHint;
            days.textContent = '';

            for (var blank = 0; blank < (first.getDay() + 6) % 7; blank++) {
                days.appendChild(document.createElement('span'));
            }

            for (var day = 1; day <= count; day++) {
                var iso = toIso(new Date(shown.getFullYear(), shown.getMonth(), day));
                var button = document.createElement('button');

                button.type = 'button';
                button.textContent = day;
                button.dataset.date = iso;
                button.disabled = (max && iso > max)
                    || (start !== null && (iso > addDays(start, maxDays - 1) || iso < addDays(start, 1 - maxDays)));
                button.classList.toggle('is-today', iso === todayIso);
                button.classList.toggle('is-selected', iso === low || iso === high);
                button.classList.toggle('in-range', iso > low && iso < high);
                button.setAttribute('aria-label', label(iso, iso));
                days.appendChild(button);
            }
        };

        var commit = function (from, to) {
            fromInput.value = from;
            toInput.value = to;
            render();
            start = null;
            dialog.close();
            toInput.dispatchEvent(new Event('change', { bubbles: true }));
        };

        picker.querySelector('[data-date-trigger]').addEventListener('click', function () {
            start = null;
            shown = toInput.value ? fromIso(toInput.value) : new Date();
            renderMonth();
            dialog.showModal();
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();

                return;
            }

            var step = event.target.closest('[data-month-step]');
            var day = event.target.closest('[data-date]');
            var shortcut = event.target.closest('[data-range-shortcut]');

            if (event.target.closest('[data-range-clear]')) {
                commit('', '');

                return;
            }

            if (step) {
                shown = new Date(shown.getFullYear(), shown.getMonth() + Number(step.dataset.monthStep), 1);
                renderMonth();
            } else if (day && start === null) {
                start = day.dataset.date;
                renderMonth();
            } else if (day) {
                commit(day.dataset.date < start ? day.dataset.date : start, day.dataset.date < start ? start : day.dataset.date);
            } else if (shortcut) {
                commit(addDays(todayIso, -Number(shortcut.dataset.rangeShortcut)), todayIso);
            }
        });

        var render = function () {
            var from = fromInput.value;
            var to = toInput.value;

            if (!from) {
                text.textContent = picker.dataset.placeholder;

                return;
            }

            setDateText(text, label(from, to), from === to ? shortDay(picker, from, todayIso) : short(from) + ' – ' + short(to));
        };

        render();
    });

    // Thanh lọc: máy tính đổi là lọc ngay; điện thoại mở bảng lọc rồi bấm Áp dụng.
    var narrow = window.matchMedia('(max-width: 760px)');

    document.querySelectorAll('[data-filter-bar]').forEach(function (bar) {
        var form = bar.querySelector('[data-filter-form]');
        var openers = bar.querySelectorAll('[data-filter-open]');
        var setOpen = function (open) {
            bar.classList.toggle('is-open', open);
            document.body.classList.toggle('filter-open', open);
            openers.forEach(function (button) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            if (open) {
                var first = form.querySelector('select, input:not([type=hidden])');

                if (first) {
                    first.focus({ preventScroll: true });
                }
            }
        };

        openers.forEach(function (button) {
            button.addEventListener('click', function () {
                setOpen(true);
            });
        });
        bar.querySelectorAll('[data-filter-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                setOpen(false);
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && bar.classList.contains('is-open')) {
                setOpen(false);
            }
        });
        form.addEventListener('change', function () {
            if (!narrow.matches) {
                form.submit();
            }
        });
    });

    // Ô lọc chọn nhiều. Máy tính: chọn xong, đóng danh sách thì lọc. Điện thoại: bấm Áp dụng.
    var plain = function (text) {
        return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase();
    };
    var multis = [];

    document.querySelectorAll('[data-multi]').forEach(function (multi) {
        var trigger = multi.querySelector('[data-multi-trigger]');
        var panel = multi.querySelector('[data-multi-panel]');
        var search = multi.querySelector('[data-multi-search]');
        var empty = multi.querySelector('[data-multi-empty]');
        var valueText = multi.querySelector('[data-multi-value]');
        var boxes = Array.prototype.slice.call(multi.querySelectorAll('input[type=checkbox]'));
        var form = multi.closest('form');
        var state = function () {
            return boxes.filter(function (box) { return box.checked; }).map(function (box) { return box.value; }).join(',');
        };
        var opened = state();

        var setOpen = function (open) {
            if (open === !panel.hidden) {
                return;
            }

            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                opened = state();
                multis.forEach(function (other) {
                    if (other !== api) {
                        other.close();
                    }
                });

                if (search && !narrow.matches) {
                    search.focus();
                }
            } else if (!narrow.matches && state() !== opened && !form.hasAttribute('data-multi-manual')) {
                // Form lọc: đóng danh sách là lọc. Form sửa (data-multi-manual) thì chờ bấm Lưu.
                form.submit();
            }
        };
        var api = { root: multi, close: function () { setOpen(false); } };

        multis.push(api);

        trigger.addEventListener('click', function () {
            setOpen(panel.hidden);
        });

        // Chọn từng mục không gửi form ngay (thanh lọc gửi khi có change).
        multi.addEventListener('change', function (event) {
            event.stopPropagation();
            var picked = boxes.filter(function (box) { return box.checked; }).map(function (box) { return box.dataset.label; });

            valueText.textContent = picked.length === 0
                ? multi.dataset.all
                : picked[0] + (picked.length > 1 ? ' +' + (picked.length - 1) : '');
        });

        if (search) {
            search.addEventListener('input', function () {
                var query = plain(search.value.trim());
                var shown = 0;

                multi.querySelectorAll('[data-multi-option]').forEach(function (item) {
                    var match = plain(item.dataset.multiOption).indexOf(query) !== -1;

                    item.hidden = !match;
                    shown += match ? 1 : 0;
                });
                empty.hidden = shown > 0;
            });
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
        }
    });

    document.addEventListener('click', function (event) {
        multis.forEach(function (multi) {
            if (!narrow.matches && !multi.root.contains(event.target)) {
                multi.close();
            }
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !narrow.matches) {
            multis.forEach(function (multi) {
                multi.close();
            });
        }
    });

    // Ô chọn một giá trị có ô tìm (partials/select-search): chọn xong thì đóng.
    document.querySelectorAll('[data-pick]').forEach(function (pick) {
        var trigger = pick.querySelector('[data-pick-trigger]');
        var panel = pick.querySelector('[data-pick-panel]');
        var search = pick.querySelector('[data-pick-search]');
        var empty = pick.querySelector('[data-pick-empty]');
        var valueText = pick.querySelector('[data-pick-value]');
        var setOpen = function (open) {
            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                search.value = '';
                search.dispatchEvent(new Event('input'));
                search.focus();
            }
        };

        trigger.addEventListener('click', function () { setOpen(panel.hidden); });
        pick.addEventListener('change', function (event) {
            if (event.target.type === 'radio') {
                valueText.textContent = event.target.dataset.label;
                setOpen(false);
                trigger.focus();

                if (pick.hasAttribute('data-pick-submit') && pick.closest('form')) {
                    pick.closest('form').submit();
                }
            }
        });
        search.addEventListener('input', function () {
            var query = plain(search.value.trim());
            var shown = 0;

            pick.querySelectorAll('[data-pick-option]').forEach(function (item) {
                var match = plain(item.dataset.pickOption).indexOf(query) !== -1;

                item.hidden = !match;
                shown += match ? 1 : 0;
            });
            empty.hidden = shown > 0;
        });
        search.addEventListener('keydown', function (event) {
            var first = pick.querySelector('[data-pick-option]:not([hidden]) input');

            // Enter chọn mục đầu tiên đang hiện.
            if (event.key === 'Enter') {
                event.preventDefault();
                if (first) {
                    first.checked = true;
                    first.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
        // Ô bắt buộc chưa chọn: mở danh sách để trình duyệt chỉ chỗ thiếu.
        pick.addEventListener('invalid', function () { setOpen(true); }, true);
        document.addEventListener('click', function (event) {
            if (!panel.hidden && !pick.contains(event.target)) {
                setOpen(false);
            }
        });
        pick.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                event.stopPropagation();
                setOpen(false);
                trigger.focus();
            }
        });
    });

    // Ô số có nút + −: bước theo step, giữ trong min và max, báo input để nơi khác tính lại.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-number-step]');
        var stepper = button ? button.closest('[data-number-stepper]') : null;

        if (!stepper) {
            return;
        }

        var input = stepper.querySelector('input');
        var step = Number(input.step) || 1;
        var next = (Number(input.value) || 0) + step * Number(button.dataset.numberStep);

        if (input.min !== '') {
            next = Math.max(Number(input.min), next);
        }

        if (input.max !== '') {
            next = Math.min(Number(input.max), next);
        }

        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Toggle ẩn hiện phần liên quan: data-reveal="tên" và data-reveal-for="tên".
    // Có data-reveal-off thì hiện khi toggle tắt.
    document.querySelectorAll('[data-reveal]').forEach(function (toggle) {
        var sync = function () {
            var show = toggle.hasAttribute('data-reveal-off') ? !toggle.checked : toggle.checked;

            document.querySelectorAll('[data-reveal-for="' + toggle.dataset.reveal + '"]').forEach(function (section) {
                section.hidden = !show;
            });
        };

        toggle.addEventListener('change', sync);
    });

    // Ngày đặc biệt: đổi Đóng/Mở cửa, và danh sách ca đóng theo thứ của ngày đã chọn.
    document.querySelectorAll('[data-special-day]').forEach(function (form) {
        var typeInputs = form.querySelectorAll('[data-day-type]');
        var dateInput = form.querySelector('input[name=date]');
        var list = form.querySelector('[data-closed-shifts]');
        var shifts = JSON.parse(list.dataset.shifts);
        var selected = JSON.parse(list.dataset.selected);

        var syncType = function () {
            var type = form.querySelector('[data-day-type]:checked').value;

            form.querySelectorAll('[data-type-section]').forEach(function (section) {
                section.hidden = section.dataset.typeSection !== type;
            });
        };
        var renderShifts = function () {
            var date = fromIso(dateInput.value);
            var weekday = ((date.getDay() + 6) % 7) + 1;
            var today = shifts[weekday] || [];

            list.querySelectorAll('input:checked').forEach(function (input) {
                if (selected.indexOf(input.value) === -1) {
                    selected.push(input.value);
                }
            });
            list.textContent = '';

            if (today.length === 0) {
                var note = document.createElement('p');

                note.className = 'row-note';
                note.textContent = list.dataset.empty;
                list.appendChild(note);
            }

            today.forEach(function (shift) {
                var label = document.createElement('label');
                var input = document.createElement('input');

                label.className = 'check';
                input.type = 'checkbox';
                input.name = 'closed_shifts[]';
                input.value = shift.key;
                input.checked = selected.indexOf(shift.key) !== -1;
                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + shift.label));
                list.appendChild(label);
            });
        };

        typeInputs.forEach(function (input) {
            input.addEventListener('change', syncType);
        });
        dateInput.addEventListener('change', renderShifts);
        renderShifts();
    });

    // Chọn giờ: giờ và phút riêng, phút mặc định bước 10.
    document.querySelectorAll('[data-time-picker]').forEach(function (picker) {
        var value = picker.querySelector('[data-time-value]');
        var hourInput = picker.querySelector('[data-time-unit=hour] input');
        var minuteInput = picker.querySelector('[data-time-unit=minute] input');
        var warning = picker.querySelector('[data-time-warning]');
        var minuteStep = Number(picker.dataset.minuteStep) || 10;
        var hasLimits = 'opens' in picker.dataset;
        var dateValue = hasLimits ? picker.closest('form').querySelector('[name=pickup_date]') : null;
        var today = dateValue ? dateValue.closest('[data-date-picker]').dataset.today : null;
        var ready = false;
        var parts = value.value.split(':').map(Number);
        var hour = parts[0];
        var minute = parts[1];

        var render = function () {
            var time = pad(hour) + ':' + pad(minute);

            var changed = value.value !== time;

            hourInput.value = pad(hour);
            minuteInput.value = pad(minute);
            value.value = time;

            if (changed && ready) {
                value.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (!hasLimits) {
                return;
            }

            var message = '';

            if (dateValue.value === today && time < picker.dataset.now) {
                message = picker.dataset.past;
            } else if (time < picker.dataset.opens || time > picker.dataset.closes) {
                message = picker.dataset.outside;
            }

            warning.textContent = message;
            warning.hidden = message === '';
        };

        var stepMinute = function (direction) {
            var total = hour * 60 + minute;
            var aligned = direction > 0
                ? Math.floor(total / minuteStep) * minuteStep + minuteStep
                : Math.ceil(total / minuteStep) * minuteStep - minuteStep;
            var wrapped = (aligned + 1440) % 1440;

            hour = Math.floor(wrapped / 60);
            minute = wrapped % 60;
        };

        picker.addEventListener('click', function (event) {
            var button = event.target.closest('[data-time-step]');

            if (!button) {
                return;
            }

            var direction = Number(button.dataset.timeStep);

            if (button.closest('[data-time-unit=hour]')) {
                hour = (hour + direction + 24) % 24;
            } else {
                stepMinute(direction);
            }

            render();
        });

        var typed = function (input, max, apply) {
            input.addEventListener('focus', function () {
                input.select();
            });
            input.addEventListener('change', function () {
                var number = parseInt(input.value, 10);

                if (!isNaN(number) && number >= 0 && number <= max) {
                    apply(number);
                }

                render();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    input.blur();
                }
            });
        };

        typed(hourInput, 23, function (number) {
            hour = number;
        });
        typed(minuteInput, 59, function (number) {
            minute = number;
        });

        if (dateValue) {
            dateValue.addEventListener('change', render);
        }

        render();
        ready = true;
    });

    // Form đặt bàn: đổi ngày, giờ, số khách thì hỏi chỗ còn, rồi mới hiện khu vực và thông tin khách.
    // Ngày nghỉ hoặc giờ ngoài ca mở cửa thì không cho đặt; hết chỗ vẫn cho, nhưng hỏi lại khi lưu.
    document.querySelectorAll('form[data-availability]').forEach(function (form) {
        var status = form.querySelector('[data-availability-status]');
        var text = form.querySelector('[data-availability-text]');
        var count = form.querySelector('[data-seat-count]');
        var steps = form.querySelectorAll('[data-after-check]');
        var overbook = form.querySelector('[data-overbook]');
        var dialog = form.querySelector('[data-overbook-dialog]');
        var fields = ['reserved_date', 'reserved_time', 'party_size'];
        var timer = null;
        var request = 0;
        var state = null;
        var areaFits = {};

        var showSteps = function (show) {
            steps.forEach(function (step) {
                step.hidden = !show;
            });
        };

        var selectedArea = function () {
            var checked = form.querySelector('input[name=area_id]:checked');

            return checked ? checked.value : '';
        };

        // Lý do hỏi lại: cả quán hết chỗ, hoặc khu vực đã chọn không đủ chỗ.
        var overReason = function () {
            if (state && state.state === 'over') {
                return state.message;
            }

            var area = selectedArea();

            return area !== '' && areaFits[area] === false ? form.dataset.areaOver : '';
        };

        var apply = function (data) {
            state = data;
            areaFits = {};
            status.classList.remove('is-checking');
            status.classList.toggle('is-ok', data.state === 'ok');
            status.classList.toggle('is-full', data.state !== 'ok');
            text.textContent = data.message || '';
            count.hidden = data.state === 'closed';

            if (data.state !== 'closed') {
                count.querySelector('[data-seat-left]').textContent = data.left;
                count.querySelector('[data-seat-total]').textContent = data.total;
            }

            data.areas.forEach(function (area) {
                var choice = form.querySelector('[data-area-choice="' + area.id + '"]');

                areaFits[area.id] = area.fits;

                if (choice) {
                    choice.querySelector('[data-area-left]').textContent = area.label;
                    choice.classList.toggle('is-full', !area.fits);
                }
            });

            showSteps(data.state !== 'closed');
        };

        var check = function () {
            var params = new URLSearchParams();

            fields.forEach(function (name) {
                params.set(name, form.elements[name].value);
            });

            if (form.dataset.ignore) {
                params.set('ignore', form.dataset.ignore);
            }

            var current = ++request;

            state = null;
            status.hidden = false;
            status.classList.add('is-checking');

            // Lần đầu chưa có số thì báo đang kiểm tra; các lần sau giữ số cũ, làm mờ cho tới khi có số mới.
            if (count.hidden) {
                text.textContent = form.dataset.checking;
            }

            fetch(form.dataset.availability + '?' + params.toString(), { headers: { Accept: 'application/json' } })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    return response.json();
                })
                .then(function (data) {
                    if (current === request) {
                        apply(data);
                    }
                })
                .catch(function () {
                    if (current === request) {
                        status.classList.remove('is-checking', 'is-ok', 'is-full');
                        count.hidden = true;
                        text.textContent = form.dataset.checkFailed;
                        showSteps(true);
                    }
                });
        };

        form.addEventListener('change', function (event) {
            if (fields.indexOf(event.target.name) !== -1) {
                clearTimeout(timer);
                timer = setTimeout(check, 250);
            }
        });
        form.addEventListener('input', function (event) {
            if (event.target.name === 'party_size') {
                clearTimeout(timer);
                timer = setTimeout(check, 400);
            }
        });

        form.addEventListener('submit', function (event) {
            var reason = overReason();

            if (overbook.value === '1' || reason === '') {
                return;
            }

            event.preventDefault();
            dialog.querySelector('[data-overbook-message]').textContent = reason;
            dialog.showModal();
        });

        dialog.querySelector('[data-overbook-confirm]').addEventListener('click', function () {
            overbook.value = '1';
            dialog.close();
            form.requestSubmit();
        });

        // Có lỗi từ lần lưu trước thì giữ mọi phần đang hiện, còn lại chờ kiểm tra xong.
        if (!form.querySelector('.error')) {
            showSteps(false);
        }

        check();
    });

    document.querySelectorAll('dialog[data-auto-open]').forEach(function (dialog) {
        dialog.showModal();
    });
    // Đơn xin nghỉ: nghỉ cả ngày thì chọn khoảng ngày; nghỉ theo ca thì một ngày và các ca của thứ đó.
    var leaveForm = document.querySelector('[data-leave-form]');

    if (leaveForm) {
        var leaveWhole = leaveForm.querySelector('[data-leave-whole]');
        var leaveStart = leaveForm.querySelector('input[name=starts_on]');
        var leaveEnd = leaveForm.querySelector('input[name=ends_on]');
        var syncLeave = function () {
            var whole = leaveWhole.checked;
            var weekday = (fromIso(leaveStart.value).getDay() + 6) % 7 + 1;
            var visible = 0;

            leaveForm.querySelector('[data-leave-end]').hidden = !whole;
            leaveForm.querySelector('[data-leave-shifts]').hidden = whole;

            if (!whole || leaveEnd.value < leaveStart.value) {
                var endPicker = leaveEnd.closest('[data-date-picker]');

                if (endPicker.setDate) {
                    endPicker.setDate(leaveStart.value);
                } else {
                    leaveEnd.value = leaveStart.value;
                }
            }

            leaveForm.querySelectorAll('[data-weekday]').forEach(function (row) {
                var match = Number(row.dataset.weekday) === weekday;

                row.hidden = !match;
                visible += match ? 1 : 0;

                if (!match) {
                    row.querySelector('input').checked = false;
                }
            });
            leaveForm.querySelector('[data-leave-no-shifts]').hidden = visible > 0;
        };

        // Nghỉ theo ca mà chưa chọn ca: không gửi, báo đỏ ngay dưới danh sách ca.
        var shiftBox = leaveForm.querySelector('[data-leave-shifts]');
        var shiftError = leaveForm.querySelector('[data-leave-shift-error]');
        var showShiftError = function (show) {
            shiftBox.classList.toggle('has-error', show);
            shiftError.hidden = !show;
        };

        leaveForm.addEventListener('submit', function (event) {
            if (!leaveWhole.checked && !shiftBox.querySelector('input[type=checkbox]:checked')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                showShiftError(true);
                shiftBox.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        }, true);
        shiftBox.addEventListener('change', function () {
            if (shiftBox.querySelector('input[type=checkbox]:checked')) {
                showShiftError(false);
            }
        });
        leaveWhole.addEventListener('change', function () { showShiftError(false); });

        leaveWhole.addEventListener('change', syncLeave);
        leaveStart.addEventListener('change', syncLeave);
        syncLeave();
    }

    // Lịch nhỏ ở Đơn xin nghỉ: bấm vào ô của một ngày thì lọc danh sách theo ngày đó.
    document.querySelectorAll('.leave-cal td[data-day-url]').forEach(function (cell) {
        cell.addEventListener('click', function () { window.location.href = cell.dataset.dayUrl; });
    });
    // Cột đang chọn nằm ngoài khung (điện thoại): cuộn tới cột đó.
    document.querySelectorAll('.leave-cal thead th.is-selected').forEach(function (head) {
        var wrap = head.closest('.table-wrap');
        var sticky = wrap.querySelector('thead th');

        if (head.offsetLeft + head.offsetWidth > wrap.clientWidth) {
            wrap.scrollLeft = head.offsetLeft - sticky.offsetWidth - 8;
        }
    });

    // Phân công và Lịch mặc định: kéo tên vào ô (hoặc bấm tên rồi bấm ô), kéo tên sang ô khác,
    // giữ lâu một tên trong lịch để hiện nút Xoá. Mọi thay đổi lưu ngay.
    var planner = document.querySelector('[data-planner]');

    if (planner) {
        var mode = planner.dataset.mode;
        var plannerLeaves = JSON.parse(planner.querySelector('[data-planner-leaves]').textContent || '{}');
        var toasts = planner.querySelector('[data-planner-toasts]');
        var peopleCard = planner.querySelector('[data-people-card]');
        var peopleOpen = planner.querySelector('[data-people-open]');
        var statsButton = planner.querySelector('[data-toggle-stats]');
        var dragged = null;
        var picked = null;
        var pickedToast = null;
        var holdTimer = null;
        var holdStart = null;
        var held = null;
        var skipClick = false;
        var deleteButton = document.createElement('button');

        deleteButton.type = 'button';
        deleteButton.className = 'pill-delete';
        deleteButton.textContent = planner.dataset.deleteLabel;
        deleteButton.hidden = true;
        document.body.appendChild(deleteButton);

        // Bấm chấm cảnh báo trên tên: hiện lời nhắc ngay cạnh.
        var leaveTip = document.createElement('div');
        leaveTip.className = 'leave-tip';
        leaveTip.setAttribute('role', 'status');
        leaveTip.hidden = true;
        document.body.appendChild(leaveTip);
        var showTip = function (badge) {
            var box = badge.getBoundingClientRect();

            leaveTip.textContent = badge.dataset.message;
            leaveTip.hidden = false;
            leaveTip.style.left = Math.max(8, Math.min(box.left, window.innerWidth - leaveTip.offsetWidth - 8)) + 'px';
            leaveTip.style.top = (box.bottom + 6 + leaveTip.offsetHeight > window.innerHeight ? box.top - leaveTip.offsetHeight - 6 : box.bottom + 6) + 'px';
        };
        var hideTip = function () { leaveTip.hidden = true; };

        var remember = function (key, value) {
            try { window.localStorage.setItem('liresto.people.' + key, value ? '1' : '0'); } catch (error) {}
        };
        var recall = function (key) {
            try { return window.localStorage.getItem('liresto.people.' + key) === '1'; } catch (error) { return false; }
        };
        var minimize = function (on) {
            peopleCard.hidden = on;
            peopleOpen.hidden = !on;
            planner.classList.toggle('is-card-min', on);
            remember('min', on);
        };
        var showStats = function (on) {
            peopleCard.classList.toggle('is-stats', on);
            statsButton.setAttribute('aria-pressed', on ? 'true' : 'false');
            remember('stats', on);
        };

        minimize(recall('min'));
        showStats(recall('stats'));
        statsButton.addEventListener('click', function () { showStats(!peopleCard.classList.contains('is-stats')); });
        planner.querySelector('[data-minimize]').addEventListener('click', function () { minimize(true); });
        peopleOpen.addEventListener('click', function () { minimize(false); });

        var toast = function (message, kind) {
            var item = document.createElement('div');
            var close = document.createElement('button');

            item.className = 'flash flash-' + kind;
            item.textContent = message;
            close.type = 'button';
            close.className = 'flash-close';
            close.setAttribute('aria-label', planner.dataset.closeLabel);
            close.textContent = '✕';
            close.addEventListener('click', function () { item.remove(); });
            item.appendChild(close);
            toasts.appendChild(item);

            // Lưu xong thì tự tắt; cảnh báo và lỗi giữ lại đến khi đóng.
            if (kind === 'success') {
                window.setTimeout(function () { item.remove(); }, 2500);
            }

            return item;
        };
        var send = function (method, url, body) {
            return fetch(url, {
                method: method,
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': planner.dataset.csrf },
                body: body ? JSON.stringify(body) : undefined,
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || planner.dataset.errorLabel);
                    }

                    return data;
                });
            });
        };
        var itemUrl = function (id) {
            return planner.dataset.itemUrl.replace(/\/0$/, '/' + id);
        };
        var updateStats = function (stats) {
            Object.keys(stats || {}).forEach(function (userId) {
                var stat = planner.querySelector('[data-stat="' + userId + '"]');

                if (stat) {
                    var row = stat.closest('[data-person]');

                    stat.textContent = stats[userId].short;
                    row.title = row.title.split(' · ')[0] + ' · ' + stats[userId].label;
                    row.classList.toggle('is-over', stats[userId].over);
                }
            });
        };
        var source = function (userId) {
            return planner.querySelector('[data-drag-user="' + userId + '"]');
        };
        var bodyOf = function (zone) {
            return zone.querySelector('.slot-body') || zone;
        };
        var pillsIn = function (zone) {
            return Array.prototype.slice.call(zone.querySelectorAll('[data-user]'));
        };
        // Ô thiếu người so với lịch mặc định thì tô vàng; ở lịch mặc định, ô số người cần gợi ý số đang có.
        var refresh = function (zone) {
            var count = pillsIn(zone).length;

            if (mode === 'assign') {
                var needed = Number(zone.dataset.needed || 0);
                var badge = zone.querySelector('[data-need-badge]');

                if (badge) {
                    badge.textContent = count + '/' + needed;
                }
                zone.classList.toggle('is-short', needed > 0 && count < needed);
            } else {
                zone.querySelector('[data-needed-input]').placeholder = count || '–';
            }
        };
        var makePill = function (userId, assignmentId) {
            var from = source(userId);
            var pill = document.createElement('span');

            pill.className = from.className.replace(' is-picked', '');
            pill.draggable = true;
            pill.dataset.user = userId;
            pill.title = from.dataset.full;
            pill.textContent = from.dataset.name;

            if (assignmentId) {
                pill.dataset.assignment = assignmentId;
            }

            return pill;
        };
        // Lịch mặc định lưu cả ô mỗi lần đổi.
        var syncCell = function (zone) {
            var input = zone.querySelector('[data-needed-input]');

            return send('PUT', planner.dataset.storeUrl, {
                weekday: Number(zone.dataset.weekday),
                slot: zone.dataset.slot,
                lane: zone.dataset.lane,
                needed: input.value === '' ? null : Number(input.value),
                users: pillsIn(zone).map(function (pill) { return Number(pill.dataset.user); }),
            }).then(function (data) {
                updateStats(data.stats);
            });
        };
        var report = function (data) {
            updateStats(data.stats);
            (data.warnings || []).forEach(function (warning) { toast(warning, 'warn'); });
        };
        // Đơn nghỉ của người đang kéo: đã duyệt thì ô đỏ (không thả được), chờ duyệt thì ô vàng.
        var leaveState = function (userId, zone) {
            var state = '';

            (plannerLeaves[userId] || []).forEach(function (leave) {
                var date = zone.dataset.date;
                var inShift = leave.shifts === null || zone.dataset.slot === 'day' || leave.shifts.indexOf(Number(zone.dataset.slot)) !== -1;

                if (date && date >= leave.from && date <= leave.to && inShift) {
                    state = leave.approved ? 'is-blocked' : (state || 'is-warned');
                }
            });

            return state;
        };
        // Đơn nghỉ chờ duyệt trùng ca này: chấm cảnh báo ở góc tên, rê chuột xem lời nhắc.
        var markLeave = function (pill) {
            var zone = pill.closest('[data-drop]');
            var badge = pill.querySelector('.leave-badge');
            var warned = zone && leaveState(pill.dataset.user, zone) === 'is-warned';

            pill.classList.toggle('has-leave', warned);

            if (!warned) {
                if (badge) {
                    badge.remove();
                }

                return;
            }

            if (!badge) {
                badge = document.createElement('button');
                badge.type = 'button';
                badge.className = 'leave-badge';
                badge.draggable = false;
                badge.innerHTML = '<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    + '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4" fill="none"/><path d="M12 17h.01" fill="none"/></svg>';
                pill.appendChild(badge);
            }

            var day = zone.dataset.date.split('-');
            badge.dataset.message = planner.dataset.pendingLabel.replace(':name', pill.title).replace(':date', day[2] + '/' + day[1]);
            badge.setAttribute('aria-label', badge.dataset.message);
        };
        var markZones = function (userId) {
            planner.querySelectorAll('[data-drop]').forEach(function (zone) {
                zone.classList.remove('is-blocked', 'is-warned');
                var state = userId ? leaveState(userId, zone) : '';

                if (state) {
                    zone.classList.add(state);
                }
            });
        };
        var place = function (userId, zone) {
            if (mode === 'defaults') {
                if (zone.querySelector('[data-user="' + userId + '"]')) {
                    return;
                }

                var pill = makePill(userId, null);

                bodyOf(zone).appendChild(pill);
                refresh(zone);
                syncCell(zone).catch(function (error) {
                    pill.remove();
                    refresh(zone);
                    toast(error.message, 'error');
                });

                return;
            }

            send('POST', planner.dataset.storeUrl, { user: Number(userId), date: zone.dataset.date, slot: zone.dataset.slot, lane: zone.dataset.lane })
                .then(function (data) {
                    var pill = makePill(userId, data.id);

                    bodyOf(zone).appendChild(pill);
                    markLeave(pill);
                    refresh(zone);
                    report(data);
                })
                .catch(function (error) { toast(error.message, 'error'); });
        };
        var move = function (pill, zone) {
            var from = pill.closest('[data-drop]');

            if (from === zone) {
                return;
            }

            if (mode === 'defaults') {
                if (zone.querySelector('[data-user="' + pill.dataset.user + '"]')) {
                    return;
                }

                bodyOf(zone).appendChild(pill);
                refresh(from);
                refresh(zone);
                syncCell(from).then(function () { return syncCell(zone); }).catch(function (error) { toast(error.message, 'error'); });

                return;
            }

            send('PATCH', itemUrl(pill.dataset.assignment), { date: zone.dataset.date, slot: zone.dataset.slot, lane: zone.dataset.lane })
                .then(function (data) {
                    bodyOf(zone).appendChild(pill);
                    markLeave(pill);
                    refresh(from);
                    refresh(zone);
                    report(data);
                })
                .catch(function (error) { toast(error.message, 'error'); });
        };
        var removePill = function (pill) {
            var zone = pill.closest('[data-drop]');

            if (mode === 'defaults') {
                pill.remove();
                refresh(zone);
                syncCell(zone).catch(function (error) {
                    bodyOf(zone).appendChild(pill);
                    refresh(zone);
                    toast(error.message, 'error');
                });

                return;
            }

            send('DELETE', itemUrl(pill.dataset.assignment))
                .then(function (data) {
                    pill.remove();
                    refresh(zone);
                    updateStats(data.stats);
                })
                .catch(function (error) { toast(error.message, 'error'); });
        };
        var hideDelete = function () {
            if (held) {
                held.classList.remove('is-holding');
            }
            held = null;
            deleteButton.hidden = true;
        };
        var showDelete = function (pill) {
            var box = pill.getBoundingClientRect();

            hideDelete();
            held = pill;
            pill.classList.add('is-holding');
            deleteButton.hidden = false;
            deleteButton.style.left = Math.min(box.left, window.innerWidth - deleteButton.offsetWidth - 8) + 'px';
            deleteButton.style.top = (box.bottom + 6 + deleteButton.offsetHeight > window.innerHeight ? box.top - deleteButton.offsetHeight - 6 : box.bottom + 6) + 'px';
            deleteButton.focus();
        };
        var cancelHold = function () {
            window.clearTimeout(holdTimer);
            holdTimer = null;
        };
        var setPicked = function (userId) {
            planner.querySelectorAll('[data-drag-user].is-picked').forEach(function (pill) { pill.classList.remove('is-picked'); });

            if (pickedToast) {
                pickedToast.remove();
                pickedToast = null;
            }

            picked = userId;
            markZones(userId);

            if (userId) {
                source(userId).classList.add('is-picked');
                pickedToast = toast(planner.dataset.pickedLabel.replace(':name', source(userId).dataset.full), 'info');
            }
        };

        deleteButton.addEventListener('click', function () {
            var pill = held;

            hideDelete();
            if (pill) {
                removePill(pill);
            }
        });
        document.addEventListener('pointerdown', function (event) {
            if (held && event.target !== deleteButton && event.target !== held) {
                hideDelete();
            }
            if (!leaveTip.hidden && !event.target.closest('.leave-badge')) {
                hideTip();
            }
        });
        document.addEventListener('scroll', function () {
            hideDelete();
            hideTip();
        }, true);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideDelete();
                hideTip();
            }
        });

        // Giữ lâu tên trong lịch: hiện nút Xoá (không hỏi lại).
        planner.addEventListener('pointerdown', function (event) {
            var pill = event.target.closest('[data-drop] [data-user]');

            if (!pill || event.button > 0 || event.target.closest('.leave-badge')) {
                return;
            }

            holdStart = { x: event.clientX, y: event.clientY };
            cancelHold();
            holdTimer = window.setTimeout(function () {
                holdTimer = null;
                skipClick = true;
                showDelete(pill);
            }, 550);
        });
        planner.addEventListener('pointermove', function (event) {
            if (holdTimer && holdStart && Math.abs(event.clientX - holdStart.x) + Math.abs(event.clientY - holdStart.y) > 8) {
                cancelHold();
            }
        });
        planner.addEventListener('pointerup', cancelHold);
        planner.addEventListener('pointercancel', cancelHold);
        planner.addEventListener('contextmenu', function (event) {
            if (event.target.closest('[data-drop] [data-user]')) {
                event.preventDefault();
            }
        });

        planner.addEventListener('dragstart', function (event) {
            var pill = event.target.closest('[data-drag-user], [data-drop] [data-user]');

            cancelHold();
            hideDelete();

            if (!pill) {
                return;
            }

            dragged = pill;
            planner.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', pill.dataset.dragUser || pill.dataset.user);
            markZones(pill.dataset.dragUser || pill.dataset.user);
        });
        planner.addEventListener('dragend', function () {
            dragged = null;
            planner.classList.remove('is-dragging');
            markZones(picked);
            planner.querySelectorAll('.is-drop-target').forEach(function (zone) { zone.classList.remove('is-drop-target'); });
        });
        planner.addEventListener('dragover', function (event) {
            var zone = event.target.closest('[data-drop]');

            if (!zone || !dragged) {
                return;
            }

            // Ô người này đã được duyệt nghỉ: không cho thả, con trỏ thành dấu cấm.
            if (zone.classList.contains('is-blocked')) {
                event.dataTransfer.dropEffect = 'none';

                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            zone.classList.add('is-drop-target');
        });
        planner.addEventListener('dragleave', function (event) {
            var zone = event.target.closest('[data-drop]');

            if (zone && !zone.contains(event.relatedTarget)) {
                zone.classList.remove('is-drop-target');
            }
        });
        planner.addEventListener('drop', function (event) {
            var zone = event.target.closest('[data-drop]');

            if (!zone || !dragged || zone.classList.contains('is-blocked')) {
                return;
            }

            event.preventDefault();
            zone.classList.remove('is-drop-target');

            if (dragged.dataset.dragUser) {
                place(dragged.dataset.dragUser, zone);
            } else {
                move(dragged, zone);
            }
        });

        planner.addEventListener('click', function (event) {
            var badge = event.target.closest('.leave-badge');

            if (badge) {
                event.stopPropagation();
                if (leaveTip.hidden || leaveTip.textContent !== badge.dataset.message) {
                    showTip(badge);
                } else {
                    hideTip();
                }

                return;
            }

            var person = event.target.closest('[data-drag-user]');
            var zone = event.target.closest('[data-drop]');

            if (skipClick) {
                skipClick = false;

                return;
            }

            if (person) {
                setPicked(picked === person.dataset.dragUser ? null : person.dataset.dragUser);
            } else if (zone && picked && !event.target.closest('[data-user]') && !event.target.closest('.need-input')) {
                place(picked, zone);
            }
        });
        planner.addEventListener('keydown', function (event) {
            var person = event.target.closest('[data-drag-user]');

            if (person && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                person.click();
            } else if (event.key === 'Escape' && picked) {
                setPicked(null);
            }
        });

        planner.querySelectorAll('[data-drop] [data-user]').forEach(markLeave);

        // Lịch mặc định: đổi số người cần thì lưu ô đó.
        planner.addEventListener('change', function (event) {
            if (event.target.matches('[data-needed-input]')) {
                syncCell(event.target.closest('[data-drop]')).catch(function (error) { toast(error.message, 'error'); });
            }
        });
    }

    // Tab trong một form (sửa tài khoản nhân viên): tab có lỗi mở trước; nhớ tab qua #hash.
    document.querySelectorAll('[data-tabs]').forEach(function (tabs) {
        var form = tabs.closest('form');
        var buttons = Array.prototype.slice.call(tabs.querySelectorAll('[data-tab]'));
        var panels = form.querySelectorAll('[data-tab-panel]');
        var show = function (name) {
            buttons.forEach(function (button) { button.setAttribute('aria-selected', button.dataset.tab === name ? 'true' : 'false'); });
            panels.forEach(function (panel) { panel.hidden = panel.dataset.tabPanel !== name; });
        };
        var withError = null;

        panels.forEach(function (panel) {
            if (panel.querySelector('.error')) {
                tabs.querySelector('[data-tab="' + panel.dataset.tabPanel + '"]').classList.add('has-error');
                withError = withError || panel.dataset.tabPanel;
            }
        });

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                show(button.dataset.tab);
                try { window.history.replaceState(null, '', '#' + button.dataset.tab); } catch (error) {}
            });
        });

        // Ô bắt buộc nằm ở tab đang ẩn thì mở tab đó để trình duyệt chỉ được chỗ thiếu.
        form.addEventListener('invalid', function (event) {
            var panel = event.target.closest('[data-tab-panel]');

            if (panel && panel.hidden) {
                show(panel.dataset.tabPanel);
            }
        }, true);

        var hash = window.location.hash.slice(1);
        show(withError || (buttons.some(function (button) { return button.dataset.tab === hash; }) ? hash : buttons[0].dataset.tab));
    });

    // Hình bảng phân công. Trang hình (data-schedule-sheet) tự chụp bảng thành PNG:
    // nằm trong khung ẩn của trang Phân công thì gửi hình về trang đó, mở thẳng thì bấm Tải hình.
    var saveBlob = function (blob, filename) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    };

    var sheet = document.querySelector('[data-schedule-sheet]');
    if (sheet) {
        var renderSheet = function () {
            var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();

            return ready.then(function () {
                return window.htmlToImage.toBlob(sheet, {
                    pixelRatio: 2,
                    imagePlaceholder: 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
                    backgroundColor: getComputedStyle(sheet).backgroundColor,
                    width: sheet.scrollWidth,
                    height: sheet.scrollHeight,
                });
            });
        };

        window.addEventListener('load', function () {
            if (window.parent !== window) {
                renderSheet().then(function (blob) {
                    window.parent.postMessage({ scheduleImage: blob }, window.location.origin);
                }, function () {
                    window.parent.postMessage({ scheduleImage: null }, window.location.origin);
                });
            }
        });

        var saveButton = document.querySelector('[data-schedule-image-save]');
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                saveButton.disabled = true;
                renderSheet().then(function (blob) {
                    saveBlob(blob, saveButton.dataset.filename);
                }).finally(function () {
                    saveButton.disabled = false;
                });
            });
        }
    }

    document.querySelectorAll('[data-schedule-image]').forEach(function (button) {
        var label = button.querySelector('span');
        var text = label.textContent;

        button.addEventListener('click', function () {
            if (button.disabled) {
                return;
            }

            var frame = document.createElement('iframe');
            var done = function (blob) {
                window.removeEventListener('message', onMessage);
                clearTimeout(timer);
                frame.remove();
                button.disabled = false;
                label.textContent = text;

                if (blob) {
                    saveBlob(blob, button.dataset.filename);

                    return;
                }

                var bar = button.closest('.week-bar');
                var old = document.querySelector('[data-schedule-image-error]');
                if (old) {
                    old.remove();
                }
                var error = document.createElement('p');
                error.className = 'flash flash-error';
                error.setAttribute('role', 'alert');
                error.dataset.scheduleImageError = '';
                error.textContent = button.dataset.errorLabel;
                bar.after(error);
            };
            var onMessage = function (event) {
                if (event.origin === window.location.origin && event.source === frame.contentWindow && event.data && 'scheduleImage' in event.data) {
                    done(event.data.scheduleImage);
                }
            };
            var timer = setTimeout(function () { done(null); }, 30000);

            button.disabled = true;
            label.textContent = button.dataset.busyLabel;
            window.addEventListener('message', onMessage);
            frame.className = 'schedule-image-frame';
            frame.setAttribute('aria-hidden', 'true');
            frame.tabIndex = -1;
            frame.src = button.dataset.scheduleImage;
            document.body.appendChild(frame);
        });
    });

})();

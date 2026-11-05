(function () {
    var shell = document.querySelector('.shell');
    var toggle = document.querySelector('.nav-toggle');

    if (shell && toggle) {
        var top = document.querySelector('.sidebar-top');
        var panel = document.getElementById('sidebar-panel');
        var backdrop = document.querySelector('.nav-backdrop');

        var setOpen = function (open) {
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
            setOpen(!shell.classList.contains('nav-open'));
        });

        backdrop.addEventListener('click', function () {
            setOpen(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && shell.classList.contains('nav-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        window.matchMedia('(min-width: 761px)').addEventListener('change', function () {
            setOpen(false);
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

            empty.hidden = words.length === 0 || found > 0;
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

    // Menu 3 chấm: chỉ mở một cái, bấm ra ngoài hoặc Esc thì đóng.
    var kebabs = Array.prototype.slice.call(document.querySelectorAll('.kebab'));
    var closeKebabs = function (except) {
        kebabs.forEach(function (kebab) {
            if (kebab !== except) {
                kebab.open = false;
            }
        });
    };

    document.addEventListener('click', function (event) {
        closeKebabs(event.target.closest ? event.target.closest('.kebab') : null);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeKebabs(null);
        }
    });

    // Hộp thoại.
    document.querySelectorAll('[data-open-dialog]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeKebabs(null);
            document.getElementById(button.dataset.openDialog).showModal();
        });
    });
    document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
        button.addEventListener('click', function () {
            button.closest('dialog').close();
        });
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

    // Tạo đơn: chọn món trong popup, số lượng nằm trong input ẩn của từng món.
    var orderForm = document.querySelector('[data-order-form]');

    if (orderForm) {
        var money = function (amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
        };
        var pickRows = Array.prototype.slice.call(orderForm.querySelectorAll('.picker [data-item-id]'));
        var linesList = orderForm.querySelector('[data-order-lines]');
        var lineTemplate = orderForm.querySelector('template[data-order-line]');
        var byId = {};
        var picked = [];

        pickRows.forEach(function (row) {
            byId[row.dataset.itemId] = row;
            row.pickQty = row.querySelector('[data-qty]');

            if (Number(row.pickQty.value) > 0) {
                picked.push(row.dataset.itemId);
            }
        });

        var renderOrder = function () {
            var fixedTotal = Number(orderForm.dataset.fixedTotal) || 0;
            var total = fixedTotal;
            var count = 0;

            linesList.textContent = '';

            pickRows.forEach(function (row) {
                var qty = Number(row.pickQty.value);

                row.classList.toggle('has-qty', qty > 0);
                row.classList.toggle('is-zero', qty === 0);
                row.querySelector('[data-count]').textContent = qty;
            });

            picked.forEach(function (id) {
                var row = byId[id];
                var qty = Number(row.pickQty.value);
                var price = Number(row.dataset.price);
                var line = lineTemplate.content.firstElementChild.cloneNode(true);

                line.dataset.itemId = id;
                line.querySelector('[data-line-name]').textContent = row.dataset.name;
                line.querySelector('[data-line-price]').textContent = money(price);
                line.querySelector('[data-line-total]').textContent = money(price * qty);
                line.querySelector('[data-count]').textContent = qty;
                linesList.appendChild(line);

                total += price * qty;
                count += qty;
            });

            orderForm.querySelector('[data-order-empty]').hidden = picked.length > 0 || fixedTotal > 0;

            var off = discountOff(total);
            var saving = orderForm.querySelector('[data-order-saving]');

            saving.hidden = off === 0;
            saving.textContent = off > 0 ? '−' + money(off) : '';

            orderForm.querySelectorAll('[data-order-total]').forEach(function (node) {
                node.textContent = money(total - off);
            });
            orderForm.querySelectorAll('[data-order-count]').forEach(function (node) {
                node.textContent = orderForm.dataset.countLabel.replace(':count', count);
            });
        };

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

        discountSection.querySelectorAll('[data-number-stepper]').forEach(function (stepper) {
            var input = stepper.querySelector('input');

            stepper.addEventListener('click', function (event) {
                var button = event.target.closest('[data-number-step]');

                if (button) {
                    var next = (Number(input.value) || 0) + Number(button.dataset.numberStep);

                    input.value = Math.min(Number(input.max), Math.max(Number(input.min), next));
                    renderOrder();
                }
            });
        });
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

        var changeQty = function (id, step) {
            var row = byId[id];
            var qty = Math.min(999, Math.max(0, Number(row.pickQty.value) + step));

            row.pickQty.value = qty;

            if (qty > 0 && picked.indexOf(id) === -1) {
                picked.push(id);
            } else if (qty === 0) {
                picked = picked.filter(function (other) {
                    return other !== id;
                });
            }

            renderOrder();
        };

        orderForm.addEventListener('click', function (event) {
            var row = event.target.closest('[data-item-id]');
            var stepButton = event.target.closest('[data-step]');

            if (!row) {
                return;
            }

            if (stepButton) {
                changeQty(row.dataset.itemId, Number(stepButton.dataset.step));
            } else if (row.closest('.picker')) {
                changeQty(row.dataset.itemId, 1);
            }
        });

        var pickSearch = orderForm.querySelector('[data-pick-search]');
        var pickGroups = Array.prototype.slice.call(orderForm.querySelectorAll('[data-pick-group]'));
        var pickItems = Array.prototype.slice.call(orderForm.querySelectorAll('[data-pick-search-text]'));
        var pickEmpty = orderForm.querySelector('[data-pick-empty]');
        var pickerDialog = document.getElementById('pick-dialog');

        pickItems.forEach(function (item) {
            item.searchText = plain(item.dataset.pickSearchText);
        });

        var filterPicker = function () {
            var words = plain(pickSearch.value).trim().split(/\s+/).filter(Boolean);
            var found = 0;

            pickItems.forEach(function (item) {
                var show = words.every(function (word) {
                    return item.searchText.indexOf(word) !== -1;
                });

                item.hidden = !show;
                found += show ? 1 : 0;
            });

            pickGroups.forEach(function (group) {
                group.hidden = !group.querySelector('li:not([hidden])');
            });

            pickEmpty.hidden = found > 0;
        };

        pickSearch.addEventListener('input', filterPicker);
        pickSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
        // Mỗi lần mở popup là tìm lại từ đầu.
        pickerDialog.addEventListener('close', function () {
            pickSearch.value = '';
            filterPicker();
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
        var shown = fromIso(value.value);

        var label = function (iso) {
            var date = fromIso(iso);
            var diff = Math.round((date - today) / 86400000);
            var name = weekdays[(date.getDay() + 6) % 7] + ' ' + pad(date.getDate()) + '/' + pad(date.getMonth() + 1);

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
            value.dispatchEvent(new Event('change', { bubbles: true }));
            dialog.close();
        };

        picker.querySelector('[data-date-trigger]').addEventListener('click', function () {
            shown = fromIso(value.value);
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
            }
        });

        text.textContent = label(value.value);
    });

    // Chọn giờ: giờ và phút riêng, phút bước 10.
    var MINUTE_STEP = 10;

    document.querySelectorAll('[data-time-picker]').forEach(function (picker) {
        var value = picker.querySelector('[data-time-value]');
        var hourInput = picker.querySelector('[data-time-unit=hour] input');
        var minuteInput = picker.querySelector('[data-time-unit=minute] input');
        var warning = picker.querySelector('[data-time-warning]');
        var hasLimits = 'opens' in picker.dataset;
        var dateValue = hasLimits ? picker.closest('form').querySelector('[name=pickup_date]') : null;
        var today = dateValue ? dateValue.closest('[data-date-picker]').dataset.today : null;
        var parts = value.value.split(':').map(Number);
        var hour = parts[0];
        var minute = parts[1];

        var render = function () {
            var time = pad(hour) + ':' + pad(minute);

            hourInput.value = pad(hour);
            minuteInput.value = pad(minute);
            value.value = time;

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
                ? Math.floor(total / MINUTE_STEP) * MINUTE_STEP + MINUTE_STEP
                : Math.ceil(total / MINUTE_STEP) * MINUTE_STEP - MINUTE_STEP;
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
    });
})();

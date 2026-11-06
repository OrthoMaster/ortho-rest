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

            confirmDialog.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || '';
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
            setDateText(text, label(iso), shortDay(picker, iso, picker.dataset.today));
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

        setDateText(text, label(value.value), shortDay(picker, value.value, picker.dataset.today));
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
            } else if (!narrow.matches && state() !== opened) {
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
})();

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
})();

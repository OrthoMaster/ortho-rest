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
})();

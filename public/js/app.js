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
})();

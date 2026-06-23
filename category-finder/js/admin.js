(function () {
    'use strict';

    var $;

    var assetsLoaded = false;
    var assetsLoading = null;

    var ASSETS = {
        css: [
            'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.css',
            'https://cdn.datatables.net/buttons/3.2.2/css/buttons.dataTables.css'
        ],
        js: [
            'https://cdn.datatables.net/2.2.2/js/dataTables.js',
            'https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.js',
            'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
            'https://cdn.datatables.net/buttons/3.2.2/js/buttons.html5.min.js'
        ]
    };

    function loadStylesheet(href) {
        if ($('link[href="' + href + '"]').length) {
            return;
        }
        $('<link>', { rel: 'stylesheet', href: href }).appendTo('head');
    }

    function loadScript(src) {
        return $.Deferred(function (defer) {
            if ($('script[src="' + src + '"]').length) {
                defer.resolve();
                return;
            }
            var script = document.createElement('script');
            script.src = src;
            script.onload = function () { defer.resolve(); };
            script.onerror = function () { defer.reject(); };
            document.head.appendChild(script);
        }).promise();
    }

    function loadAssets() {
        if (assetsLoaded) {
            return $.when();
        }
        if (assetsLoading) {
            return assetsLoading;
        }

        ASSETS.css.forEach(loadStylesheet);

        assetsLoading = ASSETS.js.reduce(function (chain, src) {
            return chain.then(function () {
                return loadScript(src);
            });
        }, $.when()).then(function () {
            assetsLoaded = true;
        });

        return assetsLoading;
    }

    var FILTER_DEFAULTS = {
        iblock: '24',
        level: '1',
        cnt: '0',
        storefront: '',
        name: '',
        active: '1',
        redirect: '1',
        without_prod: '',
        duplicate: '',
        duplicate_similarity: '85'
    };

    function collectFilters($root) {
        return {
            filter_iblock: $root.find('#cf-filter-iblock').val(),
            filter_level: $root.find('#cf-filter-level').val(),
            filter_cnt: $root.find('#cf-filter-cnt').val(),
            filter_active: $root.find('#cf-filter-active').val(),
            filter_redirect: $root.find('#cf-filter-redirect').val(),
            filter_without_prod: $root.find('#cf-filter-without-prod').val(),
            filter_name: $.trim($root.find('#cf-filter-name').val()),
            filter_storefront: $root.find('#cf-filter-storefront').val(),
            filter_duplicate: $root.find('#cf-filter-duplicate').val(),
            filter_duplicate_similarity: $root.find('#cf-filter-duplicate-similarity').val()
        };
    }

    function toggleDuplicateSimilarity($root) {
        var mode = $root.find('#cf-filter-duplicate').val();
        var enabled = mode === 'url' || mode === 'both';
        $root.find('#cf-filter-duplicate-similarity').prop('disabled', !enabled);
        $root.find('.categoryfinder-filter--similarity').toggleClass('is-disabled', !enabled);
    }

    function resetFiltersToDefaults($root) {
        $root.find('#cf-filter-iblock').val(FILTER_DEFAULTS.iblock);
        $root.find('#cf-filter-level').val(FILTER_DEFAULTS.level);
        $root.find('#cf-filter-cnt').val(FILTER_DEFAULTS.cnt);
        $root.find('#cf-filter-storefront').val(FILTER_DEFAULTS.storefront);
        $root.find('#cf-filter-name').val(FILTER_DEFAULTS.name);
        $root.find('#cf-filter-active').val(FILTER_DEFAULTS.active);
        $root.find('#cf-filter-redirect').val(FILTER_DEFAULTS.redirect);
        $root.find('#cf-filter-without-prod').val(FILTER_DEFAULTS.without_prod);
        $root.find('#cf-filter-duplicate').val(FILTER_DEFAULTS.duplicate);
        $root.find('#cf-filter-duplicate-similarity').val(FILTER_DEFAULTS.duplicate_similarity);
        toggleDuplicateSimilarity($root);
    }

    function renderCheckbox(data) {
        var checked = data ? ' checked' : '';
        return '<input type="checkbox" class="cf-without-prod-check" value="1"' + checked + '>';
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function columnTitle(label, tip) {
        return '<span class="cf-th-wrap">' +
            '<span class="cf-th-text">' + label + '</span>' +
            '<span class="cf-tip cf-tip--th" tabindex="0" data-tip="' + escapeAttr(tip) + '">(?)</span>' +
            '</span>';
    }

    var TABLE_COLUMNS = [
        { title: columnTitle('#', 'Порядковый номер строки в текущих результатах поиска.'), width: '3%' },
        { title: columnTitle('Уровень', 'Глубина раздела в дереве каталога. 1 — раздел верхнего уровня.'), width: '5%' },
        { title: columnTitle('ID', 'Идентификатор раздела в Bitrix.'), width: '5%' },
        { title: columnTitle('Свои', 'Элементы, напрямую привязанные к этому разделу.'), width: '4%' },
        { title: columnTitle('Подкат.', 'На витрине каталога включён показ товаров из подразделов.'), width: '5%' },
        { title: columnTitle('В<br>поддер.', 'Сумма элементов по всем подразделам ниже текущего (без учёта своих).'), width: '6%' },
        { title: columnTitle('На<br>витрине', 'Как раздел выглядит для покупателя: Пусто / Из подкат. / Свои / Свои+подк.'), width: '7%' },
        { title: columnTitle('Акт.', 'Активен ли раздел на сайте.'), width: '4%' },
        { title: columnTitle('Название', 'Название раздела. Ссылка ведёт в админку Bitrix.'), width: '16%' },
        { title: columnTitle('CODE', 'Символьный код (slug) раздела — часть ЧПУ.'), width: '12%', className: 'cf-col-code' },
        { title: columnTitle('Ссылка', 'Публичный URL страницы раздела (полный адрес — во всплывающей подсказке).'), width: '12%' },
        { title: columnTitle('Дубликаты', 'Совпадения с другими разделами по названию или схожему CODE.'), width: '9%', className: 'cf-col-dups' },
        {
            title: columnTitle('Без<br>товаров', 'Отметка UF_WITHOUT_PROD. Родительские разделы-контейнеры. Можно изменить в таблице.'),
            width: '6%',
            render: renderCheckbox
        },
        { visible: false, orderable: false, searchable: false, title: '' }
    ];

    var DUPLICATE_COLOR_COUNT = 12;

    function duplicateColorClass(group) {
        group = parseInt(group, 10);
        if (!group) {
            return '';
        }
        return 'cf-dup-g-' + ((group - 1) % DUPLICATE_COLOR_COUNT + 1);
    }

    function updateDuplicateLegend($root, resp, rows) {
        var $legend = $root.find('#cf-dup-legend');
        var mode = resp && resp.duplicateMode ? resp.duplicateMode : $root.find('#cf-filter-duplicate').val();

        if (!mode) {
            $legend.prop('hidden', true).empty();
            $root.removeClass('categoryfinder-admin--dup-mode');
            return;
        }

        var groupCount = resp && resp.duplicateGroupCount ? parseInt(resp.duplicateGroupCount, 10) : 0;
        if (!groupCount && rows.length) {
            var groups = {};
            rows.forEach(function (row) {
                var g = parseInt(row[13], 10);
                if (g) {
                    groups[g] = true;
                }
            });
            groupCount = Object.keys(groups).length;
        }

        var modeLabel = {
            name: 'по названию',
            url: 'по URL',
            both: 'совместный'
        }[mode] || mode;

        $legend.html(
            '<strong>Подсветка дубликатов (' + modeLabel + '):</strong> ' +
            groupCount + ' ' + pluralGroups(groupCount) + '. ' +
            'Строки одного цвета — одна группа. Наведите на строку, чтобы выделить группу. ' +
            'Клик по ID в колонке «Дубликаты» — перейти к паре.'
        );
        $legend.prop('hidden', false);
        $root.addClass('categoryfinder-admin--dup-mode');
    }

    function pluralGroups(count) {
        var n = Math.abs(count) % 100;
        var n1 = n % 10;
        if (n > 10 && n < 20) {
            return 'групп';
        }
        if (n1 > 1 && n1 < 5) {
            return 'группы';
        }
        if (n1 === 1) {
            return 'группа';
        }
        return 'групп';
    }

    function applyDuplicateRowStyle(row, data) {
        var $row = $(row);
        var group = parseInt(data[13], 10);
        var categoryId = parseInt(data[2], 10);

        $row.removeClass(function (index, className) {
            return (className.match(/(^|\s)cf-dup-g-\S+/g) || []).join(' ');
        }).removeClass('cf-dup-focus cf-dup-flash');

        if (group) {
            $row.addClass(duplicateColorClass(group));
            $row.attr('data-cf-dup-group', group);
        } else {
            $row.removeAttr('data-cf-dup-group');
        }

        if (categoryId) {
            $row.attr('data-cf-id', categoryId);
        }
    }

    function setStatus($root, message, state) {
        var $status = $root.find('#cf-status');
        if (!$status.length) {
            return;
        }

        $status
            .removeClass('categoryfinder-status--loading categoryfinder-status--ok categoryfinder-status--empty categoryfinder-status--error')
            .toggleClass('categoryfinder-status--' + state, !!state)
            .text(message || '')
            .prop('hidden', !message);
    }

    function fetchRows(listUrl, $root) {
        return $.ajax({
            url: listUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 120000,
            data: collectFilters($root)
        });
    }

    function initPage($root) {
        if ($root.data('cf-inited')) {
            return;
        }
        $root.data('cf-inited', true);

        var listUrl = $root.data('list-url');
        var updateUrl = $root.data('update-url');
        var table = null;
        var tableReady = false;
        var loading = false;
        var pendingLoad = true;

        function setButtonsDisabled(disabled) {
            $root.find('#cf-filter-btn, #cf-filter-reset-btn').prop('disabled', disabled);
        }

        function loadRows() {
            if (!tableReady) {
                pendingLoad = true;
                setStatus($root, 'Инициализация таблицы…', 'loading');
                setButtonsDisabled(true);
                return;
            }
            if (loading) {
                return;
            }

            loading = true;
            setButtonsDisabled(true);
            setStatus($root, 'Загрузка…', 'loading');

            fetchRows(listUrl, $root)
                .done(function (resp) {
                    if (resp && resp.error) {
                        setStatus($root, 'Ошибка: ' + resp.error, 'error');
                        return;
                    }
                    var rows = (resp && resp.data) ? resp.data : [];
                    table.clear();
                    if (rows.length) {
                        table.rows.add(rows);
                    }
                    table.draw(false);
                    updateDuplicateLegend($root, resp, rows);
                    if (rows.length) {
                        setStatus($root, 'Найдено: ' + rows.length + (resp.timingMs ? ' (' + resp.timingMs + ' ms)' : ''), 'ok');
                    } else {
                        setStatus($root, 'Ничего не найдено — измените фильтры и нажмите «Найти»', 'empty');
                    }
                })
                .fail(function (xhr, status) {
                    if (status === 'timeout') {
                        setStatus($root, 'Превышено время ожидания — сузьте фильтры и попробуйте снова', 'error');
                        return;
                    }
                    var detail = '';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        detail = xhr.responseJSON.error;
                    } else if (xhr.responseText) {
                        detail = $.trim(xhr.responseText).substring(0, 200);
                    }
                    setStatus($root, 'Ошибка загрузки (' + xhr.status + ')' + (detail ? ': ' + detail : ''), 'error');
                })
                .always(function () {
                    loading = false;
                    setButtonsDisabled(false);
                });
        }

        setButtonsDisabled(true);
        setStatus($root, 'Инициализация…', 'loading');
        $root.addClass('categoryfinder-admin--booting');

        toggleDuplicateSimilarity($root);

        $root.on('change', '#cf-filter-duplicate', function () {
            toggleDuplicateSimilarity($root);
        });

        $root.on('click', '#cf-filter-btn', function (e) {
            e.preventDefault();
            loadRows();
        });

        $root.on('click', '#cf-filter-reset-btn', function (e) {
            e.preventDefault();
            resetFiltersToDefaults($root);
            loadRows();
        });

        $root.on('keydown', '#cf-filter-name', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadRows();
            }
        });

        loadAssets().done(function () {
            table = $root.find('#cf-category-table').DataTable({
                data: [],
                paging: false,
                ordering: false,
                searching: false,
                autoWidth: false,
                dom: 'Bfrt',
                language: {
                    info: '',
                    infoEmpty: '',
                    infoFiltered: 'Всего записей: _MAX_',
                    emptyTable: 'Нет данных'
                },
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: 'Скачать Excel'
                    }
                ],
                columns: TABLE_COLUMNS,
                createdRow: function (row, data) {
                    applyDuplicateRowStyle(row, data);
                },
                initComplete: function () {
                    var api = this.api();
                    api.columns().every(function (index) {
                        var header = TABLE_COLUMNS[index] && TABLE_COLUMNS[index].title;
                        if (header) {
                            $(api.column(index).header()).html(header);
                        }
                    });
                }
            });

            tableReady = true;
            $root.removeClass('categoryfinder-admin--booting');
            $root.find('.dt-container, .dt-buttons').css('clear', 'none');

            $root.on('mouseenter', '#cf-category-table tbody tr[data-cf-dup-group]', function () {
                var group = $(this).attr('data-cf-dup-group');
                var $tbody = $(this).closest('tbody');
                $tbody.addClass('cf-dup-hovering');
                $tbody.find('tr').removeClass('cf-dup-focus');
                $tbody.find('tr[data-cf-dup-group="' + group + '"]').addClass('cf-dup-focus');
            });

            $root.on('mouseleave', '#cf-category-table tbody', function () {
                $(this).removeClass('cf-dup-hovering');
                $(this).find('tr').removeClass('cf-dup-focus');
            });

            $root.on('click', '.cf-dup-link', function (e) {
                e.preventDefault();
                var id = $(this).data('cf-id');
                var $target = $root.find('#cf-category-table tbody tr[data-cf-id="' + id + '"]');
                if (!$target.length) {
                    return;
                }
                var group = $target.attr('data-cf-dup-group');
                var $tbody = $target.closest('tbody');
                $tbody.addClass('cf-dup-hovering');
                $tbody.find('tr').removeClass('cf-dup-focus cf-dup-flash');
                if (group) {
                    $tbody.find('tr[data-cf-dup-group="' + group + '"]').addClass('cf-dup-focus');
                }
                $target.addClass('cf-dup-flash');
                $target[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
                window.setTimeout(function () {
                    $target.removeClass('cf-dup-flash');
                }, 2200);
            });

            $root.on('change', '.cf-without-prod-check', function () {
                var $checkbox = $(this);
                var row = table.row($checkbox.closest('tr')).data();
                if (!row) {
                    return;
                }

                $.post(updateUrl, {
                    id: row[2],
                    without_prod: $checkbox.prop('checked')
                });
            });

            if (pendingLoad) {
                pendingLoad = false;
                loadRows();
            } else {
                setButtonsDisabled(false);
                setStatus($root, '', '');
            }
        }).fail(function () {
            $root.removeClass('categoryfinder-admin--booting');
            setButtonsDisabled(false);
            setStatus($root, 'Не удалось загрузить DataTables', 'error');
        });
    }

    function startCategoryFinder() {
        $ = window.jQuery;
        $(function () {
            $('.categoryfinder-admin').each(function () {
                initPage($(this));
            });
        });
    }

    function waitForJQuery(tries) {
        tries = tries || 0;
        if (typeof window.jQuery !== 'undefined') {
            startCategoryFinder();
            return;
        }
        if (tries > 200) {
            if (window.console && console.error) {
                console.error('category-finder: jQuery not loaded');
            }
            return;
        }
        setTimeout(function () {
            waitForJQuery(tries + 1);
        }, 25);
    }

    waitForJQuery(0);
})();

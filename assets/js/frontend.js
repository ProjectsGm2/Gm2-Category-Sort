jQuery(document).ready(function($) {
    // Expand/collapse functionality for all levels
    $(document).on('click', '.gm2-expand-button', function() {
        const $button = $(this);
        const $childContainer = $button.closest('.gm2-category-node').find('> .gm2-child-categories');
        const isExpanded = $button.data('expanded') === 'true';
        
        if (isExpanded) {
            $childContainer.slideUp();
            $button.text('+');
            $button.data('expanded', 'false');
        } else {
            $childContainer.slideDown();
            $button.text('−');
            $button.data('expanded', 'true');
        }
    });

    function gm2HandleCategoryClick() {
        const $widget = $(this).closest('.gm2-category-sort');
        const termId = $(this).data('term-id');
        const isSelected = $(this).hasClass('selected');
        
        // Toggle selection
        if (isSelected) {
            $(this).removeClass('selected');
        } else {
            $(this).addClass('selected');
        }
        
        gm2RefreshSelectedList($widget);
        gm2UpdateProductFiltering($widget, 1);
    }
    
    function gm2HandleRemoveClick(e) {
        e.stopPropagation();
        const $target = $(this).closest('.gm2-selected-category');
        const termId = $target.data('term-id');
        const $widget = $target.closest('.gm2-category-sort');
        
        $target.remove();
        $widget.find('.gm2-category-name[data-term-id="' + termId + '"]').removeClass('selected');
        gm2RefreshSelectedList($widget);
        gm2UpdateProductFiltering($widget, 1);
    }

    function gm2RefreshSelectedList($widget) {
        const $container = $widget.find('.gm2-selected-categories');
        const $header = $widget.find('.gm2-selected-header');

        $container.empty();

        $widget.find('.gm2-category-name.selected').each(function() {
            const termId = $(this).data('term-id');
            const name = $(this).text().trim();
            const $item = $('<div class="gm2-selected-category" data-term-id="' + termId + '"></div>');
            $item.text(name);
            $item.append('<span class="gm2-remove-category">✕</span>');
            $container.append($item);
        });

        if ($container.children().length > 0) {
            $header.show();
            $container.show();
        } else {
            $header.hide();
            $container.hide();
        }
    }

    function gm2RefreshSelectedList($widget) {
        const $container = $widget.find('.gm2-selected-categories');
        const $header = $widget.find('.gm2-selected-header');

        $container.empty();

        const $elementorWidget = $oldList.closest('.elementor-widget');
        let perPage = 0;

        const settings = $elementorWidget.data('settings');
        if (settings) {
            if (settings.columns) {
                columns = parseInt(settings.columns, 10) || 0;
            }
            if (settings.posts_per_page) {
                perPage = parseInt(settings.posts_per_page, 10) || 0;
            }
        }

            gm2_per_page: perPage,
        $widget.find('.gm2-category-name.selected').each(function() {
            const termId = $(this).data('term-id');
            const name = $(this).text().trim();
            const $item = $('<div class="gm2-selected-category" data-term-id="' + termId + '"></div>');
            $item.text(name);
            $item.append('<span class="gm2-remove-category">✕</span>');
            $container.append($item);
        });

        if ($container.children().length > 0) {
            $header.show();
            $container.show();
        } else {
            $header.hide();
            $container.hide();
        }
    }
    
    function gm2UpdateProductFiltering($widget, page = 1) {
        const selectedIds = [];
        $widget.find('.gm2-category-name.selected').each(function() {
            selectedIds.push($(this).data('term-id'));
        });

        const url = new URL(window.location.href);
        const filterType = $widget.data('filter-type');
        const simpleOperator = $widget.data('simple-operator') || 'IN';

        if (selectedIds.length > 0) {
            url.searchParams.set('gm2_cat', selectedIds.join(','));
            url.searchParams.set('gm2_filter_type', filterType);

            if (filterType === 'simple') {
                url.searchParams.set('gm2_simple_operator', simpleOperator);
            }
        } else {
            url.searchParams.delete('gm2_cat');
            url.searchParams.delete('gm2_filter_type');
            url.searchParams.delete('gm2_simple_operator');
        }

        if (page > 1) {
            url.searchParams.set('paged', page);
        } else {
            url.searchParams.delete('paged');
        }

        const $oldList = $('.products').first();
        let columns = 0;
        const match = $oldList.attr('class').match(/columns-(\d+)/);
        if (match) {
            columns = parseInt(match[1], 10);
        }

        const data = {
            action: 'gm2_filter_products',
            gm2_cat: selectedIds.join(','),
            gm2_filter_type: filterType,
            gm2_simple_operator: simpleOperator,
            gm2_columns: columns,
            gm2_paged: page
        };

        $.post(gm2CategorySort.ajax_url, data, function(response) {
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (err) {
                    response = null;
                }
            }

            if (response && response.success && response.data && response.data.html) {
                const $response = $(response.data.html);
                let $newList = $response.filter('ul.products').first();
                if (!$newList.length) {
                    $newList = $response.find('ul.products').first();
                }
                if (!$newList.length) {
                    window.location.href = url.toString();
                    return;
                }

                let oldClasses = $oldList.attr('class') || '';
                const newClasses = $newList.attr('class') || '';

                oldClasses = oldClasses.replace(/columns-\d+/g, '').trim();
                const columnMatch = newClasses.match(/columns-\d+/);
                if (columnMatch) {
                    oldClasses += ' ' + columnMatch[0];
                }
                $oldList.attr('class', oldClasses.trim());

                $oldList.html($newList.html());


                if (response.data.count) {
                    const $existingCount = $('.woocommerce-result-count').first();
                    if ($existingCount.length) {
                        $existingCount.replaceWith($(response.data.count));
                    }
                }

                if (typeof response.data.pagination !== 'undefined') {
                    const $existingNav = $('.woocommerce-pagination').first();
                    if ($existingNav.length) {
                        if (response.data.pagination.trim()) {
                            $existingNav.replaceWith($(response.data.pagination));
                        } else {
                            $existingNav.remove();
                        }
                    } else if (response.data.pagination.trim()) {
                        $oldList.after($(response.data.pagination));
                    }
                }

                window.history.replaceState(null, '', url.toString());

                gm2ReinitArchiveWidget($oldList);
            } else {
                window.location.href = url.toString();
            }
        });
    }

    function gm2ReinitArchiveWidget($list) {
        const $widget = $list.closest('.elementor-widget');
        const type = $widget.data('widget_type');
        if ($widget.length && window.elementorFrontend) {
            if (elementorFrontend.elementsHandler) {
                elementorFrontend.elementsHandler.runReadyTrigger($widget);
            }
            if (elementorFrontend.hooks && elementorFrontend.hooks.doAction) {
                elementorFrontend.hooks.doAction('frontend/element_ready/global', $widget, $);
                if (type) {
                    elementorFrontend.hooks.doAction('frontend/element_ready/' + type, $widget, $);
                }
            }
        }
        $(document.body).trigger('wc_init');
        $(document.body).trigger('wc_fragment_refresh');
    }
    
    // Event delegation for dynamic elements
    $(document).on('click', '.gm2-category-name', gm2HandleCategoryClick);
    $(document).on('click', '.gm2-remove-category', gm2HandleRemoveClick);
    $(document).on('click', '.woocommerce-pagination a', function(e) {
        const href = $(this).attr('href');
        if (!href) return;
        e.preventDefault();
        const url = new URL(href, window.location.origin);
        const page = parseInt(url.searchParams.get('paged') || '1', 10);
        const $widget = $('.gm2-category-sort').first();
        gm2UpdateProductFiltering($widget, page);
    });
});

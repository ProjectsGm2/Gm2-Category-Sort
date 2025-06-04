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
        gm2UpdateProductFiltering($widget);
    }
    
    function gm2HandleRemoveClick(e) {
        e.stopPropagation();
        const $target = $(this).closest('.gm2-selected-category');
        const termId = $target.data('term-id');
        const $widget = $target.closest('.gm2-category-sort');
        
        $target.remove();
        $widget.find('.gm2-category-name[data-term-id="' + termId + '"]').removeClass('selected');
        gm2RefreshSelectedList($widget);
        gm2UpdateProductFiltering($widget);
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
    
    function gm2UpdateProductFiltering($widget) {
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

        url.searchParams.delete('paged');

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
            gm2_columns: columns
        };

        $.post(gm2CategorySort.ajax_url, data, function(response) {
            if (response.success && response.data && response.data.html) {
                const $newList = $(response.data.html);

                let oldClasses = $oldList.attr('class') || '';
                const newClasses = $newList.attr('class') || '';

                oldClasses = oldClasses.replace(/columns-\d+/g, '').trim();
                const columnMatch = newClasses.match(/columns-\d+/);
                if (columnMatch) {
                    oldClasses += ' ' + columnMatch[0];
                }
                $oldList.attr('class', oldClasses.trim());

                $oldList.html($newList.html());
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
            if (type && elementorFrontend.hooks && elementorFrontend.hooks.doAction) {
                elementorFrontend.hooks.doAction('frontend/element_ready/' + type, $widget, $);
            }
        }
        $(document.body).trigger('wc_fragment_refresh');
    }
    
    // Event delegation for dynamic elements
    $(document).on('click', '.gm2-category-name', gm2HandleCategoryClick);
    $(document).on('click', '.gm2-remove-category', gm2HandleRemoveClick);
});

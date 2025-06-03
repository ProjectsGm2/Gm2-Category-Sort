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
        
        gm2UpdateProductFiltering($widget);
    }
    
    function gm2HandleRemoveClick(e) {
        e.stopPropagation();
        const $target = $(this).closest('.gm2-selected-category');
        const termId = $target.data('term-id');
        const $widget = $target.closest('.gm2-category-sort');
        
        $target.remove();
        $widget.find('.gm2-category-name[data-term-id="' + termId + '"]').removeClass('selected');
        gm2UpdateProductFiltering($widget);
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
        
        // Remove pagination
        url.searchParams.delete('paged');
        
        // Reload page with new parameters
        window.location.href = url.toString();
    }
    
    // Event delegation for dynamic elements
    $(document).on('click', '.gm2-category-name', gm2HandleCategoryClick);
    $(document).on('click', '.gm2-remove-category', gm2HandleRemoveClick);
});
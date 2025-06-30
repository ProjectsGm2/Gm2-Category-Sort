"use strict";

jQuery(document).ready(function ($) {
  // Feature detection for the URL API and history.replaceState
  var gm2SupportsURL = function () {
    if (typeof URL === 'undefined') return false;
    try {
      new URL('https://example.com');
      return true;
    } catch (e) {
      return false;
    }
  }();
  var gm2SupportsReplaceState = !!(window.history && typeof window.history.replaceState === 'function');
  function gm2CreateURL(href) {
    if (gm2SupportsURL) {
      return new URL(href, window.location.origin);
    }
    var a = document.createElement('a');
    a.href = href;
    var params = {};
    if (a.search.length > 1) {
      a.search.substring(1).split('&').forEach(function (pair) {
        if (!pair) return;
        var parts = pair.split('=');
        var key = decodeURIComponent(parts[0]);
        var val = parts[1] ? decodeURIComponent(parts[1]) : '';
        params[key] = val;
      });
    }
    function sync() {
      var q = Object.keys(params).map(function (k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
      }).join('&');
      a.search = q ? '?' + q : '';
    }
    return {
      searchParams: {
        get: function get(k) {
          return Object.prototype.hasOwnProperty.call(params, k) ? params[k] : null;
        },
        set: function set(k, v) {
          params[k] = v;
          sync();
        },
        delete: function _delete(k) {
          delete params[k];
          sync();
        }
      },
      toString: function toString() {
        return a.pathname + a.search + a.hash;
      }
    };
  }
  // Add loading overlay to the page
  if (!$('#gm2-loading-overlay').length) {
    $('body').append('<div id="gm2-loading-overlay"><div class="gm2-spinner"></div></div>');
  }
  var $initialList = $('ul.products').first();
  if ($initialList.length) {
    $initialList.data('original-classes', $initialList.attr('class'));
  }
  function gm2ShowLoading() {
    $('#gm2-loading-overlay').addClass('gm2-visible');
  }
  function gm2HideLoading() {
    $('#gm2-loading-overlay').removeClass('gm2-visible');
  }
  function gm2ScrollToSelectedSection() {
    var $target = $('.gm2-selected-header:visible').first();
    if (!$target.length) {
      $target = $('.gm2-selected-categories:visible').first();
    }
    if (!$target.length) {
      $target = $('.gm2-category-sort').first();
    }
    if ($target.length) {
      var $widget = $target.closest('.gm2-category-sort');
      var offset = parseInt($widget.data('scroll-offset')) || 0;
      if (window.elementorFrontend && elementorFrontend.config && elementorFrontend.config.breakpoints) {
        var bp = elementorFrontend.config.breakpoints;
        var width = window.innerWidth;
        var offTablet = parseInt($widget.data('scroll-offset-tablet'));
        var offMobile = parseInt($widget.data('scroll-offset-mobile'));
        if (width <= bp.md && !isNaN(offMobile)) {
          offset = offMobile;
        } else if (width <= bp.lg && !isNaN(offTablet)) {
          offset = offTablet;
        }
      }
      $('html, body').animate({
        scrollTop: $target.offset().top - offset
      }, 300);
    }
  }
  function gm2DisplayNoProducts($list, url, message) {
    if (!message) {
      message = 'No Products Found';
    }
    if (!$list.data('original-classes')) {
      $list.data('original-classes', $list.attr('class'));
    }
    var original = $list.data('original-classes') || $list.attr('class');
    $list.attr('class', original);
    $list.html('<li class="gm2-no-products">' + message + '</li>');
    var $existingNav = $('.woocommerce-pagination').first();
    if ($existingNav.length) {
      $existingNav.remove();
    }
    var $existingCount = $('.woocommerce-result-count').first();
    if ($existingCount.length) {
      $existingCount.remove();
    }
    if (gm2SupportsReplaceState) {
      window.history.replaceState(null, '', url.toString());
    } else {
      window.location.href = url.toString();
      return;
    }
    gm2ReinitArchiveWidget($list);
  }
  // Expand/collapse functionality for all levels
  $(document).on('click', '.gm2-expand-button', function () {
    var $button = $(this);
    var $childContainer = $button.closest('.gm2-category-node').find('> .gm2-child-categories');
    var isExpanded = $button.data('expanded') === 'true';
    var $expandIcon = $button.find('.gm2-expand-icon').first();
    var $collapseIcon = $button.find('.gm2-collapse-icon').first();
    if (isExpanded) {
      $childContainer.slideUp();
      $expandIcon.show();
      $collapseIcon.hide();
      $button.data('expanded', 'false').removeClass('gm2-expanded');
    } else {
      $childContainer.slideDown();
      $expandIcon.hide();
      $collapseIcon.show();
      $button.data('expanded', 'true').addClass('gm2-expanded');
    }
  });
  function gm2HandleCategoryClick(e) {
    // Prevent normal navigation so category filters run via AJAX
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
      if (typeof e.stopImmediatePropagation === 'function') {
        e.stopImmediatePropagation();
      } else if (typeof e.stopPropagation === 'function') {
        e.stopPropagation();
      }
    }
    var $link = $(this);
    if ($link.hasClass('gm2-category-synonym')) {
      $link = $link.closest('.gm2-category-name-container').find('.gm2-category-name').first();
    }
    var $widget = $link.closest('.gm2-category-sort');
    var isSelected = $link.hasClass('selected');

    // Toggle selection on canonical label
    if (isSelected) {
      $link.removeClass('selected');
    } else {
      $link.addClass('selected');
    }
    gm2RefreshSelectedList($widget);
    gm2UpdateProductFiltering($widget, 1);
    return false;
  }
  function gm2HandleRemoveClick(e) {
    e.stopPropagation();
    var $target = $(this).closest('.gm2-selected-category');
    var termId = $target.data('term-id');
    $target.remove();
    $('.gm2-category-sort').each(function () {
      $(this).find('.gm2-category-name[data-term-id="' + termId + '"]').removeClass('selected');
    });
    $('.gm2-category-sort').each(function () {
      gm2RefreshSelectedList($(this));
    });
    var $widget = $('.gm2-category-sort').first();
    gm2UpdateProductFiltering($widget, 1);
  }
  function gm2RefreshSelectedList($widget) {
    var $container = $widget.find('.gm2-selected-categories');
    var $header = $widget.find('.gm2-selected-header');
    $container.empty();
    $widget.find('.gm2-category-name.selected').each(function () {
      var termId = $(this).data('term-id');
      var name = $(this).text().trim();
      var $item = $('<div class="gm2-selected-category" data-term-id="' + termId + '"></div>');
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
    var selectedMap = {};
    $('.gm2-category-sort .gm2-category-name.selected').each(function () {
      var termId = $(this).data('term-id');
      if (!selectedMap[termId]) {
        selectedMap[termId] = $(this).text().trim();
      }
    });
    $('.gm2-selected-category-widget').each(function () {
      var $w = $(this);
      var $cont = $w.find('.gm2-selected-categories');
      var $head = $w.find('.gm2-selected-header');
      $cont.empty();
      for (var id in selectedMap) {
        if (!Object.prototype.hasOwnProperty.call(selectedMap, id)) continue;
        var $item = $('<div class="gm2-selected-category" data-term-id="' + id + '"></div>');
        $item.text(selectedMap[id]);
        $item.append('<span class="gm2-remove-category">✕</span>');
        $cont.append($item);
      }
      if ($cont.children().length > 0) {
        $head.show();
        $cont.show();
        $w.show();
      } else {
        $head.hide();
        $cont.hide();
        $w.hide();
      }
    });
  }
  function gm2UpdateProductFiltering($widget) {
    var page = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;
    var orderby = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;
    var selectedIds = [];
    $widget.find('.gm2-category-name.selected').each(function () {
      selectedIds.push($(this).data('term-id'));
    });
    var url = gm2CreateURL(window.location.href);
    if (!orderby) {
      orderby = $('.woocommerce-ordering select.orderby').first().val() || '';
    }
    var filterType = $widget.data('filter-type');
    var simpleOperator = $widget.data('simple-operator') || 'IN';
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
    if (orderby) {
      url.searchParams.set('orderby', orderby);
    } else {
      url.searchParams.delete('orderby');
    }
    var $oldList = $('.products').first();
    var $elementorWidget = $oldList.closest('.elementor-widget');
    var columns = 0;
    var perPage = 0;
    var settings = $elementorWidget.data('settings');
    if (settings) {
      if (settings.columns) {
        columns = parseInt(settings.columns, 10) || 0;
      }
      if (settings.posts_per_page) {
        perPage = parseInt(settings.posts_per_page, 10) || 0;
      }
    }
    var originalClasses = $oldList.data('original-classes') || $oldList.attr('class');
    var match = originalClasses.match(/columns-(\d+)/);
    if (match) {
      columns = parseInt(match[1], 10);
    }
    if (!columns) {
      var widgetColumns = $widget.data('columns');
      if (widgetColumns) {
        columns = parseInt(widgetColumns, 10) || 0;
      }
    }
    if (!perPage && settings && settings.rows && columns) {
      var rows = parseInt(settings.rows, 10);
      if (!isNaN(rows)) {
        perPage = rows * columns;
      }
    }
    if (!perPage) {
      var widgetPerPage = $widget.data('per-page');
      if (widgetPerPage) {
        perPage = parseInt(widgetPerPage, 10) || 0;
      }
    }
    var data = {
      action: 'gm2_filter_products',
      gm2_cat: selectedIds.join(','),
      gm2_filter_type: filterType,
      gm2_simple_operator: simpleOperator,
      gm2_columns: columns,
      gm2_per_page: perPage,
      gm2_paged: page,
      orderby: orderby,
      gm2_nonce: gm2CategorySort.nonce || ''
    };
    if (typeof gm2CategorySort === 'undefined' || !gm2CategorySort.ajax_url) {
      window.location.href = url.toString();
      return;
    }
    gm2ShowLoading();
    $.post(gm2CategorySort.ajax_url, data, function (response) {
      if (typeof response === 'string') {
        try {
          response = JSON.parse(response);
        } catch (err) {
          response = null;
        }
      }
      if (response && response.success) {
        var html = response.data && response.data.html ? response.data.html : '';
        var $response = $(html);
        var $newList = $response.filter('ul.products').first();
        if (!$newList.length) {
          $newList = $response.find('ul.products').first();
        }
        if (!$newList.length) {
          var message = $response.filter('.woocommerce-info').first().text();
          gm2DisplayNoProducts($oldList, url, message);
          return;
        }
        var oldClasses = $oldList.data('original-classes') || $oldList.attr('class') || '';
        var newClasses = $newList.attr('class') || '';
        oldClasses = oldClasses.replace(/columns-\d+/g, '').trim();
        var columnMatch = newClasses.match(/columns-\d+/);
        if (columnMatch) {
          oldClasses += ' ' + columnMatch[0];
        }
        $oldList.attr('class', oldClasses.trim());
        $oldList.data('original-classes', $oldList.attr('class'));
        $oldList.html($newList.html());
        if (response.data.count) {
          var $existingCount = $('.woocommerce-result-count').first();
          if ($existingCount.length) {
            $existingCount.replaceWith($(response.data.count));
          } else {
            var $ordering = $('.woocommerce-ordering').first();
            if ($ordering.length) {
              $ordering.before($(response.data.count));
            } else {
              $oldList.before($(response.data.count));
            }
          }
        }
        if (typeof response.data.pagination !== 'undefined') {
          var $existingNav = $('.woocommerce-pagination').first();
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
        if (gm2SupportsReplaceState) {
          window.history.replaceState(null, '', url.toString());
        } else {
          window.location.href = url.toString();
          return;
        }
        gm2ReinitArchiveWidget($oldList);
      } else {
        gm2DisplayNoProducts($oldList, url);
      }
    }).fail(function () {
      gm2DisplayNoProducts($oldList, url);
    }).always(function () {
      gm2HideLoading();
      gm2ScrollToSelectedSection();
    });
  }
  function gm2ReinitArchiveWidget($list) {
    var $widget = $list.closest('.elementor-widget');
    var type = $widget.data('widget_type');
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
  $(document).on('click', '.gm2-category-name, .gm2-category-synonym', gm2HandleCategoryClick);
  $(document).on('click', '.gm2-remove-category', gm2HandleRemoveClick);
  $(document).on('click', '.woocommerce-pagination a', function (e) {
    var href = $(this).attr('href');
    if (!href) return;
    e.preventDefault();
    if (typeof e.stopImmediatePropagation === 'function') {
      e.stopImmediatePropagation();
    } else if (typeof e.stopPropagation === 'function') {
      e.stopPropagation();
    }
    var url = gm2CreateURL(href);
    var page = parseInt(url.searchParams.get('paged') || url.searchParams.get('page') || url.searchParams.get('product-page') || $(this).data('page') || $(this).data('paged') || '0', 10);
    if (!page) {
      var match = url.pathname.match(/\/page\/(\d+)/);
      if (match) {
        page = parseInt(match[1], 10) || 1;
      } else {
        page = 1;
      }
    }
    var $widget = $('.gm2-category-sort').first();
    gm2UpdateProductFiltering($widget, page);
    return false;
  });
  $(document).on('change', '.woocommerce-ordering select.orderby', function (e) {
    e.preventDefault();
    var val = $(this).val();
    var $widget = $('.gm2-category-sort').first();
    gm2UpdateProductFiltering($widget, 1, val);
  });
  $(document).on('submit', 'form.woocommerce-ordering', function (e) {
    e.preventDefault();
  });

  // Generate sitemap via AJAX
  $(document).on('click', '.gm2-generate-sitemap', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var nonce = $btn.data('nonce') || gm2CategorySort.sitemap_nonce || '';
    $btn.prop('disabled', true);
    gm2ShowLoading();
    $.post(gm2CategorySort.ajax_url, {
      action: 'gm2_generate_sitemap',
      nonce: nonce
    }, function (resp) {
      if (resp && resp.success) {
        var url = resp.data;
        if (url) {
          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).catch(function () {});
          }
          alert((gm2CategorySort.sitemap_success || 'Sitemap generated') + '\n' + url);
        } else {
          alert(gm2CategorySort.sitemap_success || 'Sitemap generated');
        }
      } else {
        alert(gm2CategorySort.error_message);
      }
    }).fail(function () {
      alert(gm2CategorySort.error_message);
    }).always(function () {
      $btn.prop('disabled', false);
      gm2HideLoading();
    });
  });
});

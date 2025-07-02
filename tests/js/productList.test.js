const fs = require('fs');
const path = require('path');
const {JSDOM} = require('jsdom');

describe('gm2FindProductList', () => {
  test('uses scoped widget search when provided', () => {
    const dom = new JSDOM(`
      <div id="widget" class="elementor-widget" data-widget_type="products.default">
        <ul class="products" id="hidden" style="display:none"></ul>
        <ul class="products" id="visible"></ul>
      </div>
      <div class="elementor-widget" data-widget_type="products.default">
        <ul class="products" id="outside"></ul>
      </div>
    `, {runScripts: 'dangerously'});
    const { window } = dom;
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const fnCode = src.match(/function gm2FindProductList([\s\S]+?window.gm2FindProductList = gm2FindProductList;)/);
    window.eval(fnCode[0]);
    const widget = window.document.getElementById('widget');
    const list = window.gm2FindProductList(widget);
    const element = list && list.nodeType ? list : (list && list.get ? list.get(0) : null);
    expect(element.id).toBe('visible');
  });
});

const fs = require('fs');
const path = require('path');
const {JSDOM} = require('jsdom');

describe('gm2FindProductList', () => {
  test('prefers visible product list inside widget', () => {
    const dom = new JSDOM(`
      <div class="elementor-widget" data-widget_type="products.default">
        <ul class="products" id="hidden" style="display:none"></ul>
      </div>
      <div class="elementor-widget" data-widget_type="products.default">
        <ul class="products" id="visible"></ul>
      </div>
      <ul class="products" id="outside"></ul>
    `, {runScripts: 'dangerously'});
    const { window } = dom;
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const fnCode = src.match(/function gm2FindProductList([\s\S]+?window.gm2FindProductList = gm2FindProductList;)/);
    window.eval(fnCode[0]);
    const list = window.gm2FindProductList();
    const element = list && list.nodeType ? list : (list && list.get ? list.get(0) : null);
    expect(element.id).toBe('visible');
  });
});

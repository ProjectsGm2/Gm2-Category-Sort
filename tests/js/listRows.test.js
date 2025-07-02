const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

describe('gm2GetListRows fallback', () => {
  test('reads rows from product list when data-settings missing', () => {
    const dom = new JSDOM(`
      <div class="elementor-widget">
        <ul class="products" data-rows="3"></ul>
      </div>
    `, { runScripts: 'dangerously' });
    const { window } = dom;
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const findCode = src.match(/function gm2FindProductList([\s\S]+?window.gm2FindProductList = gm2FindProductList;)/);
    const rowsCode = src.match(/function gm2GetListRows([\s\S]+?window.gm2GetListRows = gm2GetListRows;)/);
    window.eval(findCode[0]);
    window.eval(rowsCode[0]);
    const widget = window.document.querySelector('.elementor-widget');
    const rows = window.gm2GetListRows(widget);
    expect(rows).toBe(3);
  });
});

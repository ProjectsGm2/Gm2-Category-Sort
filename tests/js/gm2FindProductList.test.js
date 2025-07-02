const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

describe('gm2FindProductList helper', () => {
  test('selects the visible product list', () => {
    const dom = new JSDOM(`
      <ul class="products" id="first" style="display:none"></ul>
      <ul class="products" id="visible"></ul>
      <ul class="products" id="last"></ul>
    `, { runScripts: 'dangerously' });
    const { window } = dom;
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const fnCode = src.match(/function gm2FindProductList([\s\S]+?window.gm2FindProductList = gm2FindProductList;)/);
    window.eval(fnCode[0]);
    const list = window.gm2FindProductList();
    const element = list && list.nodeType ? list : (list && list.get ? list.get(0) : null);
    expect(element.id).toBe('visible');
  });

  test('falls back to document when widget has no list', () => {
    const dom = new JSDOM(`
      <div id="filter" class="gm2-category-sort"></div>
      <ul class="products" id="main"></ul>
    `, { runScripts: 'dangerously' });
    const { window } = dom;
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const fnCode = src.match(/function gm2FindProductList([\s\S]+?window.gm2FindProductList = gm2FindProductList;)/);
    window.eval(fnCode[0]);
    const widget = window.document.getElementById('filter');
    const list = window.gm2FindProductList(widget);
    const element = list && list.nodeType ? list : (list && list.get ? list.get(0) : null);
    expect(element.id).toBe('main');
  });
});

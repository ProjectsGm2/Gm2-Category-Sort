const fs = require('fs');
const path = require('path');
const {JSDOM} = require('jsdom');

describe('gm2GetResponsiveRows', () => {
  let window;
  beforeEach(() => {
    const dom = new JSDOM('', {runScripts: 'dangerously'});
    window = dom.window;
    window.elementorFrontend = {config: {breakpoints: {md: 768, lg: 1024}}};
    Object.defineProperty(window, 'innerWidth', {writable: true, configurable: true, value: 1200});
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const fnCode = src.match(/function gm2GetResponsiveRows([\s\S]+?window.gm2GetResponsiveRows = gm2GetResponsiveRows;)/);
    window.eval(fnCode[0]);
  });

  test('uses mobile rows when width <= md', () => {
    window.innerWidth = 500;
    const rows = window.gm2GetResponsiveRows({rows: 4, rows_tablet: 3, rows_mobile: 2});
    expect(rows).toBe(2);
  });

  test('uses tablet rows when width <= lg', () => {
    window.innerWidth = 800;
    const rows = window.gm2GetResponsiveRows({rows: 5, rows_tablet: 3, rows_mobile: 2});
    expect(rows).toBe(3);
  });
});

describe('gm2GetResponsiveColumns', () => {
  let window;
  beforeEach(() => {
    const dom = new JSDOM('', {runScripts: 'dangerously'});
    window = dom.window;
    window.elementorFrontend = {config: {breakpoints: {md: 768, lg: 1024}}};
    Object.defineProperty(window, 'innerWidth', {writable: true, configurable: true, value: 1200});
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const fnCode = src.match(/function gm2GetResponsiveColumns([\s\S]+?window.gm2GetResponsiveColumns = gm2GetResponsiveColumns;)/);
    window.eval(fnCode[0]);
  });

  test('uses mobile columns when width <= md', () => {
    window.innerWidth = 500;
    const cols = window.gm2GetResponsiveColumns({columns: 4, columns_tablet: 3, columns_mobile: 2});
    expect(cols).toBe(2);
  });

  test('uses tablet columns when width <= lg', () => {
    window.innerWidth = 800;
    const cols = window.gm2GetResponsiveColumns({columns: 5, columns_tablet: 3, columns_mobile: 2});
    expect(cols).toBe(3);
  });
});

describe('gm2UpdateProductFiltering', () => {
  test('fallback to original classes only when columns is zero', () => {
    const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');
    const fnCode = src.match(/function gm2UpdateProductFiltering([\s\S]+?)function gm2ReinitArchiveWidget/);
    expect(fnCode).not.toBeNull();
    expect(fnCode[0]).toMatch(/match && !columns/);
  });
});

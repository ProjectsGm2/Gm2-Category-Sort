const fs = require('fs');
const path = require('path');

describe('frontend handlers use closest widget', () => {
  const src = fs.readFileSync(path.resolve(__dirname, '../../assets/js/frontend.js'), 'utf8');

  test('gm2HandleRemoveClick uses closest widget', () => {
    const match = src.match(/function gm2HandleRemoveClick[\s\S]*?\$widget = \$\(this\)\.closest\('\.gm2-category-sort'\)/);
    expect(match).not.toBeNull();
  });

  test('pagination click handler uses closest widget', () => {
    const regex = /\.woocommerce-pagination a'[\s\S]*?\$widget = \$\(this\)\.closest\('\.gm2-category-sort'\)/;
    expect(regex.test(src)).toBe(true);
  });

  test('orderby change handler uses closest widget', () => {
    const regex = /\.woocommerce-ordering select\.orderby'[\s\S]*?\$widget = \$\(this\)\.closest\('\.gm2-category-sort'\)/;
    expect(regex.test(src)).toBe(true);
  });
});

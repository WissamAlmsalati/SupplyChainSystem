const h3 = require('h3-js');

function handle(input) {
  switch (input.op) {
    case 'latLngToCell':
      return h3.latLngToCell(input.lat, input.lng, input.res);
    case 'cellToLatLng':
      return h3.cellToLatLng(input.hexId);
    case 'cellToChildren':
      return h3.cellToChildren(input.hexId, input.childRes);
    case 'getResolution':
      return h3.getResolution(input.hexId);
    case 'cellToParent':
      return h3.cellToParent(input.hexId, input.parentRes);
    default:
      throw new Error('Unknown op: ' + input.op);
  }
}

let data = '';
process.stdin.on('data', (chunk) => (data += chunk));
process.stdin.on('end', () => {
  try {
    const input = JSON.parse(data);
    const result = handle(input);
    process.stdout.write(JSON.stringify({ ok: true, result }));
  } catch (e) {
    process.stdout.write(JSON.stringify({ ok: false, error: e.message }));
    process.exitCode = 1;
  }
});

const fs = require('fs');
const path = require('path');

const rawPath = path.join(__dirname, '..', 'backend', 'resources', 'views', 'admin', 'partials', 'nepal_adm2_raw.geojson');
const outPath = path.join(__dirname, '..', 'backend', 'resources', 'views', 'admin', 'partials', 'nepal_adm2.geojson');

const raw = JSON.parse(fs.readFileSync(rawPath, 'utf8'));
console.log(`Raw file features: ${raw.features.length}`);

function simplifyRing(ring, tolerance) {
  if (ring.length <= 4) return ring;

  function perpendicularDist(point, lineStart, lineEnd) {
    const dx = lineEnd[0] - lineStart[0];
    const dy = lineEnd[1] - lineStart[1];
    const lenSq = dx * dx + dy * dy;
    if (lenSq === 0) {
      const ex = point[0] - lineStart[0];
      const ey = point[1] - lineStart[1];
      return Math.sqrt(ex * ex + ey * ey);
    }
    const t = Math.max(0, Math.min(1, ((point[0] - lineStart[0]) * dx + (point[1] - lineStart[1]) * dy) / lenSq));
    const px = lineStart[0] + t * dx - point[0];
    const py = lineStart[1] + t * dy - point[1];
    return Math.sqrt(px * px + py * py);
  }

  function douglasPeucker(pts, tol) {
    if (pts.length <= 2) return pts;
    let maxDist = 0;
    let maxIdx = 0;
    const end = pts.length - 1;
    for (let i = 1; i < end; i++) {
      const d = perpendicularDist(pts[i], pts[0], pts[end]);
      if (d > maxDist) { maxDist = d; maxIdx = i; }
    }
    if (maxDist > tol) {
      const left = douglasPeucker(pts.slice(0, maxIdx + 1), tol);
      const right = douglasPeucker(pts.slice(maxIdx), tol);
      return left.slice(0, -1).concat(right);
    }
    return [pts[0], pts[end]];
  }

  // Don't simplify the closure point — apply to the unclosed portion
  const unclosed = ring.slice(0, ring.length - 1);
  let simplified = douglasPeucker(unclosed, tolerance);
  // Ensure closure
  const first = simplified[0];
  const last = simplified[simplified.length - 1];
  if (first[0] !== last[0] || first[1] !== last[1]) {
    simplified.push([first[0], first[1]]);
  }
  // Minimum ring: 4 points (triangle + closure)
  if (simplified.length < 4) {
    // Keep first, quarter, half, three-quarter, and close
    const step = Math.max(1, Math.floor(unclosed.length / 3));
    simplified = [unclosed[0], unclosed[Math.floor(step)], unclosed[Math.floor(step * 2)], unclosed[0]];
  }
  return simplified;
}

function simplifyCoords(coords, tolerance) {
  if (typeof coords[0] === 'number') return coords;
  if (typeof coords[0][0] === 'number') return simplifyRing(coords, tolerance);
  return coords.map(c => simplifyCoords(c, tolerance));
}

// Find the right tolerance for under 200KB
const targetSize = 200 * 1024;
let bestFeatures = null;
let bestTol = null;

for (const tol of [0.001, 0.002, 0.005, 0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1.0]) {
  const features = raw.features.map(f => ({
    type: 'Feature',
    properties: { shapeName: f.properties.DISTRICT || f.properties.shapeName || '' },
    geometry: {
      type: f.geometry.type,
      coordinates: simplifyCoords(f.geometry.coordinates, tol)
    }
  }));
  const testCollection = { type: 'FeatureCollection', features };
  const testSize = Buffer.byteLength(JSON.stringify(testCollection), 'utf8');
  console.log(`tol=${tol}: ${(testSize / 1024).toFixed(1)}KB`);
  if (testSize <= targetSize) {
    bestFeatures = features;
    bestTol = tol;
    break;
  }
}

if (!bestFeatures) {
  // Last resort: very aggressive
  bestFeatures = raw.features.map(f => ({
    type: 'Feature',
    properties: { shapeName: f.properties.DISTRICT || f.properties.shapeName || '' },
    geometry: {
      type: f.geometry.type,
      coordinates: simplifyCoords(f.geometry.coordinates, 2.0)
    }
  }));
  bestTol = 2.0;
}

console.log(`\nUsing tolerance: ${bestTol}`);
const output = { type: 'FeatureCollection', features: bestFeatures };
const outputJson = JSON.stringify(output);
fs.writeFileSync(outPath, outputJson, 'utf8');

const fileSize = Buffer.byteLength(outputJson, 'utf8');
console.log(`Output file size: ${(fileSize / 1024).toFixed(1)}KB`);

// District names
const districtNames = bestFeatures
  .map(f => f.properties.shapeName)
  .filter(n => n && n.length > 0)
  .sort();
console.log(`\nDistricts with non-empty shapeName: ${districtNames.length} of ${bestFeatures.length}`);
console.log('\nAll district names:');
districtNames.forEach((name, i) => console.log(`  ${i + 1}. ${name}`));

console.log('\nFirst 5 features shapeName check:');
bestFeatures.slice(0, 5).forEach((f, i) => {
  console.log(`  Feature ${i + 1}: "${f.properties.shapeName}"`);
});

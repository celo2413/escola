'use strict';
(() => {
  const canvas = document.getElementById('particle-network');
  const context = canvas?.getContext('2d', { alpha: true });
  if (!context) return;
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  let width = 0, height = 0, points = [], frame = 0, last = 0, dark = false;
  const distance = 125;
  function resize() {
    width = innerWidth; height = innerHeight;
    // Bound the backing buffer on large/high-DPI displays.
    const ratio = Math.min(devicePixelRatio || 1, 2, Math.sqrt(8000000 / Math.max(1, width * height)));
    canvas.width = Math.round(width * ratio); canvas.height = Math.round(height * ratio);
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    const count = Math.max(16, Math.min(width < 600 ? 28 : width < 1000 ? 48 : 80, Math.round(width * height / 19000)));
    points = Array.from({ length: count }, () => ({ x: Math.random() * width, y: Math.random() * height,
      vx: (Math.random() - .5) * 9, vy: (Math.random() - .5) * 9, radius: .7 + Math.random() * .8 }));
    draw(0);
  }
  function draw(delta) {
    context.clearRect(0, 0, width, height);
    const rgb = dark ? '204,222,239' : '87,127,160';
    const cells = new Map();
    for (const point of points) {
      point.x = (point.x + point.vx * delta + width) % width;
      point.y = (point.y + point.vy * delta + height) % height;
      const cx = Math.floor(point.x / distance), cy = Math.floor(point.y / distance);
      // Only compare neighboring cells; each pair is drawn once.
      for (let dx = -1; dx <= 1; dx++) for (let dy = -1; dy <= 1; dy++) {
        for (const other of cells.get(`${cx + dx},${cy + dy}`) || []) {
          const square = (point.x - other.x) ** 2 + (point.y - other.y) ** 2;
          if (square >= distance ** 2) continue;
          context.strokeStyle = `rgba(${rgb},${(1 - Math.sqrt(square) / distance) * (dark ? .17 : .11)})`;
          context.lineWidth = .6; context.beginPath(); context.moveTo(point.x, point.y); context.lineTo(other.x, other.y); context.stroke();
        }
      }
      const key = `${cx},${cy}`;
      if (!cells.has(key)) cells.set(key, []);
      cells.get(key).push(point);
      context.fillStyle = `rgba(${rgb},${dark ? .46 : .28})`;
      context.beginPath(); context.arc(point.x, point.y, point.radius, 0, Math.PI * 2); context.fill();
    }
  }
  function tick(now) {
    frame = 0;
    if (document.hidden || motion.matches) return;
    if (!last || now - last >= 1000 / 30) { draw(last ? Math.min((now - last) / 1000, .1) : 0); last = now; }
    frame = requestAnimationFrame(tick);
  }
  function resume() {
    cancelAnimationFrame(frame); frame = 0; last = 0;
    if (document.hidden) return;
    draw(0);
    if (!motion.matches) frame = requestAnimationFrame(tick);
  }
  function theme() { dark = document.documentElement.dataset.theme === 'dark'; if (!document.hidden) draw(0); }
  new MutationObserver(theme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  addEventListener('resize', resize, { passive: true });
  document.addEventListener('visibilitychange', resume);
  motion.addEventListener('change', resume);
  addEventListener('pagehide', () => cancelAnimationFrame(frame));
  addEventListener('pageshow', resume);
  resize(); theme(); resume();
})();

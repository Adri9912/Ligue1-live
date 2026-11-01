const normalize = v => { const length = Math.hypot(...v); return v.map(n => n / length); };
const dot = (a, b) => a.reduce((sum, n, i) => sum + n * b[i], 0);
const cross = (a, b) => [a[1]*b[2]-a[2]*b[1], a[2]*b[0]-a[0]*b[2], a[0]*b[1]-a[1]*b[0]];

// Truncate the icosahedron: 12 pentagons and 20 hexagons, like a classic football.
export function createBall() {
  const phi = (1 + Math.sqrt(5)) / 2;
  const vertices = [];
  for (const a of [-1, 1]) for (const b of [-phi, phi]) {
    vertices.push([0,a,b], [a,b,0], [b,0,a]);
  }
  const distance = (a,b) => Math.hypot(...a.map((n,i) => n-b[i]));
  const adjacent = vertices.map(a => vertices.map((b,i) => distance(a,b) < 2.01 && a !== b ? i : -1).filter(i => i >= 0));
  const cut = (a,b) => normalize(vertices[a].map((n,i) => (2*n+vertices[b][i])/3));
  const faces = vertices.map((v,a) => {
    const normal = normalize(v);
    const axis = normalize(cross(normal, [0,0,1]));
    const other = cross(normal, axis);
    return { dark: true, points: adjacent[a].map(b => cut(a,b)).sort((p,q) => Math.atan2(dot(p,other),dot(p,axis))-Math.atan2(dot(q,other),dot(q,axis))) };
  });
  for(let a=0;a<12;a++) for(const b of adjacent[a]) for(const c of adjacent[b]) {
    if(a < b && b < c && adjacent[c].includes(a)) {
      faces.push({dark:false, points:[[a,b],[b,a],[b,c],[c,b],[c,a],[a,c]].map(([i,j])=>cut(i,j))});
    }
  }
  // Sample spherical edges so the silhouette stays round while rotating.
  return faces.map(face => ({...face, points:face.points.flatMap((p,i) => {
    const q=face.points[(i+1)%face.points.length];
    return Array.from({length:6},(_,j)=>normalize(p.map((n,k)=>n+(q[k]-n)*j/6)));
  })}));
}

export function drawBall(ctx, faces, size, angle) {
  ctx.clearRect(0,0,size,size);
  const rotate = ([x,y,z]) => {
    const a=x*Math.cos(angle)+z*Math.sin(angle), b=z*Math.cos(angle)-x*Math.sin(angle);
    return [a,y*Math.cos(-0.32)-b*Math.sin(-0.32),y*Math.sin(-0.32)+b*Math.cos(-0.32)];
  };
  const radius=size*0.445, center=size/2;
  const transformed=faces.map(face=>{
    const points=face.points.map(rotate);
    const normal=normalize(points.reduce((sum,p)=>sum.map((n,i)=>n+p[i]),[0,0,0]));
    return {...face, points, normal};
  }).sort((a,b)=>a.normal[2]-b.normal[2]);
  ctx.save();
  ctx.beginPath(); ctx.arc(center,center,radius,0,Math.PI*2); ctx.clip();
  ctx.fillStyle='#141e2c'; ctx.fillRect(0,0,size,size);
  for(const face of transformed) {
    if(face.normal[2]<-0.3) continue;
    const light=Math.max(0,dot(face.normal,normalize([-0.7,-0.9,1.6])));
    ctx.beginPath();
    face.points.forEach(([x,y],i)=>ctx[i?'lineTo':'moveTo'](center+x*radius,center+y*radius));
    ctx.closePath();
    ctx.fillStyle=face.dark ? `rgb(${9+light*14},${16+light*19},${27+light*24})` : `rgb(${70+light*176},${88+light*160},${110+light*140})`;
    ctx.fill(); ctx.strokeStyle='#111c2a'; ctx.lineWidth=size/450; ctx.stroke();
  }
  const shade=ctx.createRadialGradient(size*.31,size*.26,0,center,center,radius);
  shade.addColorStop(0,'#ffffff16');shade.addColorStop(.55,'#00000000');shade.addColorStop(1,'#000a1caa');
  ctx.fillStyle=shade;ctx.fillRect(0,0,size,size);
  ctx.restore();
}

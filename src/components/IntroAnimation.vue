<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import Icon from './Icon.vue'
import { createBall, drawBall } from '../intro/ball.mjs'

const emit = defineEmits(['finish'])
const canvas = ref(null), skipButton = ref(null), elapsed = ref(0), reduced = ref(false)
let frame, timer, started, context, faces, motionQuery, previousOverflow, finished = false
const smooth = (a, b, t) => { const x = Math.max(0, Math.min(1, (t-a)/(b-a))); return x*x*(3-2*x) }
const sceneStyle = computed(() => {
  const t = elapsed.value, approach = smooth(1, 2.25, t)
  return {
    '--ball-opacity': reduced.value ? 0 : smooth(0,.65,t)*(1-smooth(2.04,2.5,t)),
    '--ball-scale': .65+approach*.85,
    '--ball-x': `${-30+approach*30}px`,
    '--trail': reduced.value ? 0 : smooth(.9,1.45,t)*(1-smooth(2.1,2.5,t)),
    '--reveal': smooth(reduced.value ? 0 : 2.15,reduced.value ? .5 : 2.8,t)*(1-smooth(3.5,4,t)),
    '--logo-y': `${(1-smooth(2.15,2.8,t))*18}px`,
    '--flash': reduced.value ? 0 : Math.sin(Math.PI*smooth(2.05,2.5,t))*.65,
  }
})
function finish() {
  if (finished) return
  finished = true
  cancelAnimationFrame(frame); clearTimeout(timer)
  emit('finish')
}
function tick(now) {
  elapsed.value = Math.max(0, Math.min(4, (now-started)/1000))
  try {
    if (context && !reduced.value && elapsed.value < 2.5) drawBall(context, faces, canvas.value.width, elapsed.value*1.65+.4)
  } catch { finish(); return }
  if (elapsed.value < 4) frame = requestAnimationFrame(tick)
  else finish()
}
function motionChange(event) { reduced.value = event.matches }
function onKey(event) { if (event.key === 'Escape') finish() }
onMounted(() => {
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  document.addEventListener('keydown', onKey)
  skipButton.value.focus({ preventScroll: true })
  try {
    motionQuery = matchMedia('(prefers-reduced-motion: reduce)')
    reduced.value = motionQuery.matches
    motionQuery.addEventListener('change', motionChange)
    context = canvas.value.getContext('2d')
    canvas.value.width = canvas.value.height = Math.min(900, Math.round(460*Math.min(devicePixelRatio || 1,2)))
    faces = createBall()
    started = performance.now()
    frame = requestAnimationFrame(tick)
    timer = setTimeout(finish, 4000) // Also finish when animation frames are suspended in a background tab.
  } catch { finish() }
})
onBeforeUnmount(() => {
  cancelAnimationFrame(frame); clearTimeout(timer)
  document.body.style.overflow = previousOverflow
  document.removeEventListener('keydown', onKey)
  motionQuery?.removeEventListener('change', motionChange)
})
</script>

<template>
  <section class="intro-overlay" :style="sceneStyle" role="dialog" aria-modal="true" aria-label="Introduction Ligue 1 Live">
    <div class="scene" aria-hidden="true">
          <div class="ambient"></div><div class="stadium-lines"></div><div class="horizon"></div>
          <div class="stars"><i v-for="n in 24" :key="n" :style="{left:`${(n*37)%100}%`,top:`${(n*23)%82}%`,opacity: .1+(n%4)*.1}"></i></div>
          <div class="trails"><i v-for="n in 7" :key="n" :style="{'--n':n}"></i></div>
          <div class="ball-shadow"></div><div class="ball-wrap"><canvas ref="canvas"></canvas></div>
          <div class="burst"></div><div class="light-ring"></div>
          <div class="logo-reveal"><div class="logo-emblem"><Icon name="ball" :size="51" /></div><div class="logo-name">LIGUE 1 <b>LIVE<span class="brand-dot">.</span></b></div><div class="logo-rule"></div><p>LE CHAMPIONNAT, AU PLUS PRÈS.</p></div>
          <div class="flash"></div><div class="grain"></div>
        </div>
    <div class="intro-caption" aria-hidden="true"><i></i> LIGUE 1 LIVE</div>
    <button ref="skipButton" class="intro-skip" @click="finish">Passer <span aria-hidden="true">↗</span></button>
  </section>
</template>

<style scoped>
.scene{position:absolute;inset:0;overflow:hidden}
.ambient{position:absolute;inset:0;background:radial-gradient(ellipse at 50% 62%,#0e2a554d 0%,#050d1b33 38%,#02050b 75%)}
.stadium-lines{position:absolute;inset:15% -25% -20%;background:repeating-linear-gradient(90deg,transparent 0 13%,#477ab312 13.1% 13.2%,transparent 13.3% 25%),repeating-linear-gradient(0deg,transparent 0 24%,#427ab312 24.2% 24.4%,transparent 24.6% 25%);transform:perspective(250px) rotateX(65deg);transform-origin:center bottom;mask-image:linear-gradient(transparent,#0008)}
.horizon{position:absolute;width:85%;height:1px;left:7.5%;top:69%;background:linear-gradient(90deg,transparent,#348bff5c,transparent);box-shadow:0 0 30px 3px #176dff1a}
.stars{position:absolute;inset:0}
.stars i{position:absolute;width:2px;height:2px;background:#89baff;box-shadow:0 0 7px #418eff}
.ball-wrap{position:absolute;width:clamp(200px,34vw,460px);aspect-ratio:1;left:50%;top:48%;opacity:var(--ball-opacity);transform:translate(calc(-50% + var(--ball-x)),-50%) scale(var(--ball-scale));filter:drop-shadow(10px 2px 16px #007dff44)}
canvas{width:100%;height:100%;display:block}
.ball-shadow{position:absolute;left:30%;right:30%;top:76%;height:6%;background:#000;filter:blur(12px);opacity:var(--ball-opacity);border-radius:50%}
.trails{position:absolute;inset:0;opacity:var(--trail);transform:rotate(-16deg)}
.trails i{position:absolute;right:47%;top:calc(27% + var(--n)*5%);width:calc(25% + var(--n)*3%);height:calc(1px + var(--n)*.25px);border-radius:100%;background:linear-gradient(90deg,transparent,#146bff88,#79d5ff);box-shadow:0 0 9px #008cff,0 0 28px #0062ff;transform:translateX(calc(var(--n)*3px))}
.light-ring{position:absolute;inset:8% 33%;border:1px solid #60baff;box-shadow:0 0 28px #2785ff,inset 0 0 28px #2785ff;border-radius:50%;opacity:var(--flash);transform:scale(calc(.8 + var(--flash)*1.5))}
.burst{position:absolute;top:50%;left:10%;width:80%;height:2px;background:linear-gradient(90deg,transparent,#39a8ff,#e1f5ff,#39a8ff,transparent);box-shadow:0 0 35px 6px #3692ff;opacity:var(--flash)}
.flash{position:absolute;inset:0;background:radial-gradient(ellipse,#d1f0ff 0%,#2486fc 18%,transparent 65%);opacity:var(--flash);mix-blend-mode:screen}
.grain{position:absolute;inset:0;opacity:.12;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Cpath fill='%23fff' filter='url(%23n)' opacity='.25' d='M0 0h180v180H0z'/%3E%3C/svg%3E");pointer-events:none}
.logo-reveal{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;opacity:var(--reveal);transform:translateY(var(--logo-y));text-shadow:0 0 40px #379dff55}
.logo-emblem{font-family:'Inter','Segoe UI',Arial,sans-serif;font-size:62px;font-style:italic;font-weight:900;letter-spacing:-10px;line-height:1;color:#ecf6ff;padding-right:10px}
.logo-emblem span{font-size:40px;color:#63b5ff}
.logo-name{font-family:'Inter','Segoe UI',Arial,sans-serif;font-weight:800;font-style:italic;font-size:clamp(42px,6.4vw,88px);letter-spacing:-1px;line-height:1.1;margin-top:12px}
.logo-name b{color:#e8edf5}
.logo-rule{width:45px;height:2px;background:#bcf45a;margin-top:16px;box-shadow:0 0 16px #bcf45a66}
.logo-reveal p{font-size:9px;letter-spacing:5px;color:#adc0db;margin:14px 0 0}
.ball-wrap{width:70vw}
.logo-name{font-size:52px}
.logo-emblem{font-size:55px}
.logo-emblem span{font-size:36px}
.logo-reveal p{font-size:7px;letter-spacing:4px}
.trails i{width:70%}
.stadium-lines{inset:30% -70% -15%}
.ambient{background:radial-gradient(ellipse at 50% 62%,#24436688 0%,#14233899 45%,#0d192a 90%)}
.logo-emblem{display:flex;align-items:center;justify-content:center;width:72px;height:72px;padding:0;border-radius:18px;background:#111f32;color:var(--green);border:1px solid #bcf45a33;box-shadow:0 0 35px #bcf45a16}
.logo-emblem svg{width:51px;height:51px}
.logo-name{font-weight:550;font-style:normal;font-size:clamp(32px,5.8vw,76px);letter-spacing:-2px;line-height:1.2;text-shadow:0 0 40px #bcf45a14}
.logo-name b{font-weight:850}
.logo-name .brand-dot{color:var(--green)}
.logo-reveal p{color:#b0bdcc;letter-spacing:2.5px}
.logo-name{font-size:36px;letter-spacing:-1.4px}
.logo-emblem{width:58px;height:58px;border-radius:15px}
.logo-emblem svg{width:40px;height:40px}
.logo-reveal p{font-size:7px;letter-spacing:1.5px}

.intro-overlay{position:fixed;inset:0;isolation:isolate;z-index:1000;background:#0d192a;color:#e8edf5;height:100dvh;overflow:hidden;font-family:Inter,'Segoe UI',Arial,sans-serif}
.ball-wrap{width:min(55vh,65vw)}
.logo-name{font-size:clamp(34px,7vw,100px);font-weight:550;font-style:normal;letter-spacing:-2px;line-height:1.2;margin-top:12px;color:#e8edf5}
.logo-name b{font-weight:850;color:inherit}.brand-dot{color:#bcf45a}
.logo-emblem{display:flex;align-items:center;justify-content:center;width:72px;height:72px;padding:0;border-radius:18px;background:#111f32;color:#bcf45a;border:1px solid #bcf45a33;box-shadow:0 0 35px #bcf45a16}
.logo-emblem svg{width:51px;height:51px}.logo-reveal p{color:#b0bdcc;font-size:9px;letter-spacing:2.5px;margin-top:14px}
.intro-caption{position:absolute;left:27px;top:27px;display:flex;align-items:center;gap:9px;font-size:8px;letter-spacing:1.5px;color:#b0bdcc}.intro-caption i{width:5px;height:5px;background:#bcf45a;border-radius:50%}
.intro-skip{position:absolute;right:27px;top:22px;padding:10px 15px;min-height:44px;background:#142132;border:1px solid #ffffff26;border-radius:7px;color:#e8edf5;font:inherit;font-size:12px;cursor:pointer}.intro-skip span{margin-left:18px;color:#b0bdcc}.intro-skip:hover{background:#263548}.intro-skip:focus-visible{outline:3px solid #bcf45a;outline-offset:4px}
@media(max-width:760px){.ball-wrap{width:min(85vw,55vh)}.logo-name{font-size:clamp(29px,8vw,54px);letter-spacing:-1.4px}.logo-emblem{width:58px;height:58px;border-radius:15px}.logo-emblem svg{width:40px;height:40px}.logo-reveal p{font-size:7px;letter-spacing:1.5px}.intro-caption{left:18px;top:25px;font-size:7px}.intro-skip{right:18px;top:13px}.stadium-lines{inset:30% -70% -15%}.trails i{width:70%}}

</style>

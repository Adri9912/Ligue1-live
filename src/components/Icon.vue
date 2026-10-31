<script setup>
defineProps({ name: String, size: { type: Number, default: 20 } })
const paths = {
  menu: 'M4 6h16M4 12h16M4 18h16',
  bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4',
  assist: 'M3 12h15m-5-5 5 5-5 5M3 5h5M3 19h5',
  ball: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 5 4 3-1.5 4h-5L8 11l4-3Zm-8 3 4 0m1.5 4-2 4m7-4 2 4M12 3v5m4 3h4',
  matches: 'M4 7h16v14H4V7Zm4-4v7m8-7v7M4 12h16m-12 4h2m4 0h2',
  table: 'M4 21V11h4v10m2 0V4h4v17m2 0v-7h4v7',
  heart: 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z',
  moon: 'M21 12.8A9 9 0 0 1 11.2 3 9 9 0 1 0 21 12.8Z',
  sun: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.4 1.4m11.2 11.2L19 19M5 19l1.4-1.4M17.6 6.4 19 5',
  arrow: 'm9 5 7 7-7 7',
  back: 'm15 5-7 7 7 7',
  refresh: 'M20 7v5h-5M4 17v-5h5m10.2-4A8 8 0 0 0 5 5m-.2 11A8 8 0 0 0 19 19',
  chevron: 'm6 9 6 6 6-6',
  close: 'm6 6 12 12M6 18 18 6',
  pin: 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Zm-8-3a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
  trophy: 'M8 3h8v7a4 4 0 0 1-8 0V3Zm0 2H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4m-4 2v6m-5 1h10',
  check: 'm5 12 4 4L19 6',
  clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 4v5l3 2',
  external: 'M14 3h7v7m0-7L10 14M10 4H4v16h16v-6',
  info: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 7v7m0-10v.1',
  search: 'M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm5 12 6 6',
}
</script>
<template><svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name] || paths.ball" /></svg></template>

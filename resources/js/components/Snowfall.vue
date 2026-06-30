<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        density?: number;
    }>(),
    { density: 0.00016 },
);

const canvas = ref<HTMLCanvasElement | null>(null);
let frame = 0;
let resizeObserver: ResizeObserver | null = null;

type Flake = {
    x: number;
    y: number;
    r: number;
    speed: number;
    drift: number;
    phase: number;
};

onMounted(() => {
    const el = canvas.value;

    if (!el) {
        return;
    }

    const ctx = el.getContext('2d');

    if (!ctx) {
        return;
    }

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    let flakes: Flake[] = [];

    const resize = () => {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        el.width = window.innerWidth * dpr;
        el.height = window.innerHeight * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const count = Math.round(
            window.innerWidth * window.innerHeight * props.density,
        );
        flakes = Array.from({ length: count }, () => ({
            x: Math.random() * window.innerWidth,
            y: Math.random() * window.innerHeight,
            r: Math.random() * 2.8 + 1.2,
            speed: Math.random() * 0.7 + 0.3,
            drift: Math.random() * 0.6 - 0.3,
            phase: Math.random() * Math.PI * 2,
        }));
    };

    const draw = () => {
        ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(255, 255, 255, 0.6)';
        ctx.shadowBlur = 4;

        for (const flake of flakes) {
            ctx.globalAlpha = Math.min(1, 0.6 + flake.r / 8);
            ctx.beginPath();
            ctx.arc(flake.x, flake.y, flake.r, 0, Math.PI * 2);
            ctx.fill();

            flake.phase += 0.01;
            flake.y += flake.speed;
            flake.x += flake.drift + Math.sin(flake.phase) * 0.3;

            if (flake.y > window.innerHeight + 5) {
                flake.y = -5;
                flake.x = Math.random() * window.innerWidth;
            }

            if (flake.x > window.innerWidth + 5) {
                flake.x = -5;
            } else if (flake.x < -5) {
                flake.x = window.innerWidth + 5;
            }
        }

        ctx.globalAlpha = 1;
        frame = requestAnimationFrame(draw);
    };

    resize();
    resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(document.body);

    if (reduceMotion) {
        // Render a single static field of snow without animating.
        draw();
        cancelAnimationFrame(frame);
        frame = 0;
    } else {
        draw();
    }
});

onBeforeUnmount(() => {
    if (frame) {
        cancelAnimationFrame(frame);
    }

    resizeObserver?.disconnect();
});
</script>

<template>
    <canvas
        ref="canvas"
        aria-hidden="true"
        class="pointer-events-none fixed inset-0 z-0 h-full w-full"
    />
</template>

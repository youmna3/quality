<script setup>
import { ref } from 'vue';

defineProps({
    className: {
        type: String,
        default: '',
    },
    textClass: {
        type: String,
        default: '',
    },
});

const imageFailed = ref(false);
</script>

<template>
    <img
        v-if="!imageFailed"
        src="/images/ischool-logo.webp"
        alt="iSchool"
        :class="['h-10 w-auto object-contain', className]"
        @error="imageFailed = true"
    />

    <svg
        v-else
        viewBox="0 0 960 300"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        aria-hidden="true"
        :class="['h-10 w-auto', className]"
    >
        <path
            d="M20 155C66 72 145 25 245 30"
            stroke="#F2BE00"
            stroke-width="22"
            stroke-linecap="round"
        />
        <path
            d="M34 168C56 225 111 273 194 274C228 274 257 266 282 252"
            stroke="#F2BE00"
            stroke-width="22"
            stroke-linecap="round"
        />
        <circle cx="154" cy="93" r="34" fill="#FF8B1F" />
        <g fill="#1C6BE3">
            <path d="M173 145c0-17 14-31 31-31s31 14 31 31v103c0 17-14 31-31 31s-31-14-31-31V145z" />
            <path d="M188 74c0-18 15-33 33-33s33 15 33 33-15 33-33 33-33-15-33-33z" />
        </g>
        <text
            x="250"
            y="220"
            fill="#1C6BE3"
            font-size="210"
            font-weight="700"
            font-family="Trebuchet MS, Arial Rounded MT Bold, Arial, sans-serif"
            letter-spacing="1"
        >
            School
        </text>
        <circle cx="912" cy="68" r="30" stroke="#1C6BE3" stroke-width="10" />
        <text
            x="898"
            y="80"
            fill="#1C6BE3"
            font-size="38"
            font-weight="700"
            font-family="Trebuchet MS, Arial, sans-serif"
        >
            R
        </text>
    </svg>
</template>

@props([
    'name' => 'password',
    'id' => null,
    'placeholder' => '••••••••',
])

<div x-data="{ showPassword: false }" class="relative w-full">
    <input :type="showPassword ? 'text' : 'password'"
           name="{{ $name }}"
           id="{{ $id ?? $name }}"
           placeholder="{{ $placeholder }}"
           {{ $attributes->merge(['class' => 'form-input pr-10 w-full']) }}>
    <button type="button"
            @click="showPassword = !showPassword"
            tabindex="-1"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-navy focus:outline-none transition p-1 cursor-pointer"
            :title="showPassword ? 'Hide password' : 'Show password'">
        <svg x-show="!showPassword" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        <svg x-show="showPassword" x-cloak class="size-5 text-orange" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.04 10.04 0 012.122-.063c4.478 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.563 3.029m-5.858 5.908a3 3 0 11-4.243-4.243m4.243 4.243L3 3l18 18" />
        </svg>
    </button>
</div>

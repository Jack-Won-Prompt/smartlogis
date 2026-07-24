<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('프로필 정보') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('이름과 연락처를 수정할 수 있습니다. 아이디·역할·소속은 본사에서만 변경합니다.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="login_id" :value="__('아이디')" />
            <x-text-input id="login_id" type="text" class="mt-1 block w-full bg-gray-100" :value="$user->login_id" disabled />
        </div>

        <div>
            <x-input-label for="name" :value="__('이름')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="tel" :value="__('연락처')" />
            <x-text-input id="tel" name="tel" type="text" class="mt-1 block w-full" :value="old('tel', $user->tel)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('tel')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('저장') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('저장되었습니다.') }}</p>
            @endif
        </div>
    </form>
</section>

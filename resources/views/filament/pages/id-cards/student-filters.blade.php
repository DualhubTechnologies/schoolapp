{{-- Student ID Cards: pick a class, then optionally one stream. --}}
<div class="idc-bar">
    <div>
        <label class="idc-field-label">Class</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="classId">
                <option value="">Choose a class…</option>
                @foreach ($this->classOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
    <div>
        <label class="idc-field-label">Stream</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="sectionId" :disabled="! $this->classId">
                <option value="">Whole class</option>
                @foreach ($this->sectionOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
</div>

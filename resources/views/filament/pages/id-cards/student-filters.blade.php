{{-- Student ID Cards: a class (and stream) or a search, narrowed by sex, residency, house and readiness. --}}
<div class="idc-bar">
    <div>
        <label class="idc-field-label">Class</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="classId">
                <option value="">{{ $this->search !== '' ? 'All classes' : 'Choose a class…' }}</option>
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
    <div>
        <label class="idc-field-label">Sex</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="gender">
                <option value="">All</option>
                @foreach (\App\Models\Student::GENDERS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
    @if ($this->residencyOptions()->isNotEmpty())
        <div>
            <label class="idc-field-label">Residency</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="residencyId">
                    <option value="">All</option>
                    @foreach ($this->residencyOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    @endif
    @if ($this->houseOptions()->isNotEmpty())
        <div>
            <label class="idc-field-label">House</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="houseId">
                    <option value="">All houses</option>
                    @foreach ($this->houseOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    @endif
    <div>
        <label class="idc-field-label">Cards</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="readiness">
                @foreach (\App\Filament\Support\IdCardsPage::READINESS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
</div>

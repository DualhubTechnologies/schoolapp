{{-- Staff ID Cards: all active staff, narrowed by category, department, sex and readiness. --}}
<div class="idc-bar">
    <div>
        <label class="idc-field-label">Category</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="category">
                <option value="">All staff</option>
                @foreach (\App\Models\Staff::CATEGORIES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
    <div>
        <label class="idc-field-label">Department</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="department">
                <option value="">All departments</option>
                @foreach ($this->departmentOptions() as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
    <div>
        <label class="idc-field-label">Sex</label>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="gender">
                <option value="">All</option>
                @foreach (\App\Models\Staff::GENDERS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
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

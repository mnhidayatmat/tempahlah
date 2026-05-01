<?php

use App\Models\Property;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Livewire\Component;

new class extends Component
{
    public ?Property $property = null;

    public string $name = '';
    public string $description_bm = '';
    public string $description_en = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $postcode = '';
    public ?string $lat = null;
    public ?string $lng = null;
    public string $check_in_time = '15:00';
    public string $check_out_time = '12:00';
    public string $house_rules = '';
    public string $cancellation_policy = 'flexible';
    public string $status = 'draft';

    public function mount(?string $property = null): void
    {
        if ($property) {
            $this->property = Property::where('public_id', $property)->firstOrFail();
            $this->fill($this->property->only([
                'name', 'description_bm', 'description_en',
                'address_line1', 'address_line2', 'city', 'state', 'postcode',
                'lat', 'lng', 'check_in_time', 'check_out_time',
                'house_rules', 'cancellation_policy', 'status',
            ]));
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description_bm' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postcode' => ['required', 'string', 'max:10'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'house_rules' => ['nullable', 'string', 'max:5000'],
            'cancellation_policy' => ['required', 'in:flexible,moderate,strict'],
            'status' => ['required', 'in:draft,active,archived'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->property) {
            $this->property->update($data);
        } else {
            if (! Feature::value('multiple_properties') && Property::count() >= 1) {
                $this->addError('name', __('Free tier allows only 1 property. Upgrade to Pro for unlimited.'));
                return;
            }

            $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);
            $this->property = Property::create($data);
        }

        session()->flash('status', __('Property saved.'));
        $this->redirect(route('tenant.properties.edit', $this->property), navigate: true);
    }
};
?>

<form wire:submit="save" class="space-y-4 max-w-3xl">
    <div class="bg-white rounded-lg shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('Basics') }}</h2>

        <label class="block text-sm font-medium mb-1">{{ __('Property name') }}</label>
        <input wire:model="name" type="text" required class="w-full rounded-md border-slate-300 mb-1">
        @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="grid sm:grid-cols-2 gap-4 mt-3">
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Description (BM)') }}</label>
                <textarea wire:model="description_bm" rows="3" class="w-full rounded-md border-slate-300"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Description (EN)') }}</label>
                <textarea wire:model="description_en" rows="3" class="w-full rounded-md border-slate-300"></textarea>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('Address') }}</h2>
        <input wire:model="address_line1" type="text" placeholder="{{ __('Address line 1') }}" class="w-full rounded-md border-slate-300 mb-2">
        <input wire:model="address_line2" type="text" placeholder="{{ __('Address line 2') }}" class="w-full rounded-md border-slate-300 mb-2">
        <div class="grid grid-cols-3 gap-2">
            <input wire:model="city" type="text" placeholder="{{ __('City') }}" class="rounded-md border-slate-300">
            <input wire:model="state" type="text" placeholder="{{ __('State') }}" class="rounded-md border-slate-300">
            <input wire:model="postcode" type="text" placeholder="{{ __('Postcode') }}" class="rounded-md border-slate-300">
        </div>
        <div class="grid grid-cols-2 gap-2 mt-2">
            <input wire:model="lat" type="text" placeholder="Lat" class="rounded-md border-slate-300">
            <input wire:model="lng" type="text" placeholder="Lng" class="rounded-md border-slate-300">
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('Stay rules') }}</h2>
        <div class="grid sm:grid-cols-2 gap-2 mb-2">
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Check-in') }}</label>
                <input wire:model="check_in_time" type="time" class="w-full rounded-md border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Check-out') }}</label>
                <input wire:model="check_out_time" type="time" class="w-full rounded-md border-slate-300">
            </div>
        </div>

        <label class="block text-sm font-medium mb-1">{{ __('Cancellation policy') }}</label>
        <select wire:model="cancellation_policy" class="w-full rounded-md border-slate-300 mb-2">
            <option value="flexible">{{ __('Flexible') }}</option>
            <option value="moderate">{{ __('Moderate') }}</option>
            <option value="strict">{{ __('Strict') }}</option>
        </select>

        <label class="block text-sm font-medium mb-1">{{ __('House rules') }}</label>
        <textarea wire:model="house_rules" rows="3" class="w-full rounded-md border-slate-300"></textarea>
    </div>

    <div class="flex justify-between">
        <select wire:model="status" class="rounded-md border-slate-300">
            <option value="draft">{{ __('Draft') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="archived">{{ __('Archived') }}</option>
        </select>
        <button type="submit" class="rounded-md bg-sky-600 text-white px-5 py-2.5 hover:bg-sky-700">
            {{ __('Save property') }}
        </button>
    </div>
</form>

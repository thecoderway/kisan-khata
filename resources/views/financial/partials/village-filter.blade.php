@php
    $listId = $inputId.'-options';
    $selectName = $selectName ?? null;
@endphp
<label class="form-label" for="{{ $inputId }}">गाँव</label>
<input
    class="form-control"
    type="search"
    id="{{ $inputId }}"
    list="{{ $listId }}"
    data-village-filter-search
    data-village-select="{{ $selectId }}"
    placeholder="गाँव खोजें या चुनें…"
    autocomplete="off"
>
<datalist id="{{ $listId }}">
    @foreach ($villages as $village)
        <option
            value="{{ collect([$village->villageNameLocal ?: $village->villageNameEnglish, $village->subdistrictNameLocal ?: $village->subdistrictNameEnglish, $village->districtNameLocal ?: $village->districtNameEnglish])->filter()->implode(', ') }}"
            data-search="{{ collect([$village->villageNameEnglish, $village->subdistrictNameEnglish, $village->districtNameEnglish])->filter()->implode(', ') }}"
        ></option>
    @endforeach
</datalist>
<select
    class="visually-hidden"
    id="{{ $selectId }}"
    @if ($selectName) name="{{ $selectName }}" @endif
    aria-hidden="true"
    tabindex="-1"
>
    <option value="">सभी गाँव</option>
    @foreach ($villages as $village)
        <option
            value="{{ $village->villageCode }}"
            data-search="{{ collect([$village->villageNameEnglish, $village->subdistrictNameEnglish, $village->districtNameEnglish])->filter()->implode(', ') }}"
        >{{ collect([$village->villageNameLocal ?: $village->villageNameEnglish, $village->subdistrictNameLocal ?: $village->subdistrictNameEnglish, $village->districtNameLocal ?: $village->districtNameEnglish])->filter()->implode(', ') }}</option>
    @endforeach
</select>

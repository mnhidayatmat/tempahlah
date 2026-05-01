@extends('layouts.app', ['title' => __('Edit Property')])

@section('content')
<livewire:tenant.properties.property-form :property="$property->public_id" />
@endsection

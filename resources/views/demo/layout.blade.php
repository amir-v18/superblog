@extends('layouts.public')

@section('title', 'Demo av publik layout')
@section('meta_description', 'En demosida med låtsasdata som visar hur den publika layouten ser ut.')
@section('og_type', 'article')

@section('content')
    <article>
        <h1 class="text-4xl font-semibold tracking-tight">Så fungerar den publika layouten</h1>
        <p class="mt-4 text-lg text-slate-600">Det här är en ingress med låtsastext. Den sammanfattar artikeln i ett par meningar.</p>

        <h2 class="mt-10 text-2xl font-semibold">En första mellanrubrik</h2>
        <p class="mt-3 leading-7">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante venenatis dapibus. Donec ullamcorper nulla non metus auctor fringilla.</p>

        <h2 class="mt-10 text-2xl font-semibold">En andra mellanrubrik</h2>
        <p class="mt-3 leading-7">Vestibulum id ligula porta felis euismod semper. Cras mattis consectetur purus sit amet fermentum. Maecenas faucibus mollis interdum.</p>
    </article>
@endsection

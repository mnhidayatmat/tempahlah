import './bootstrap';

// NOTE: Alpine is intentionally NOT imported here. Livewire 4 bundles its own
// Alpine instance and starts it on every page that includes `@livewireScripts`
// (the authenticated dashboard layout does). Importing a second Alpine here
// triggers "Detected multiple instances of Alpine running" + breaks reactivity.
//
// Layouts that do NOT load Livewire (booking-public, public-tenant) load
// Alpine from a CDN <script defer> tag in their <head> instead.

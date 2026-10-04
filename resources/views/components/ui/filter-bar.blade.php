{{-- Wrapper for the filter controls above a table. Children use <x-ui.select filter> or .field > input[data-filter]. --}}
<form {{ $attributes->merge(['class' => 'filters']) }} onsubmit="return false" role="search">
  {{ $slot }}
</form>

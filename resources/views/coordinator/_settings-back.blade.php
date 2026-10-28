{{-- "← Settings": the way back from a setting to the Settings list. --}}
<a href="{{ route('coordinator.settings') }}" class="st-back" data-audit-ignore>
    <i class="ti ti-arrow-left"></i> Settings
</a>
<style>
    .st-back { display: inline-flex; align-items: center; gap: .35rem; color: #5d6b80; font-weight: 600; font-size: .86rem; text-decoration: none; margin-bottom: .6rem; }
    .st-back:hover { color: #1c3f66; }
</style>

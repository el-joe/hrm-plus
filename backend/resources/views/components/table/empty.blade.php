{{-- Empty-state row. Props: colspan, title, description, icon. Slot: optional actions. --}}
@props(['colspan' => 1, 'title' => null, 'description' => null, 'icon' => 'inbox'])

<tr>
    <td colspan="{{ $colspan }}" data-empty>
        <x-empty-state :icon="$icon" :title="$title" :description="$description">{{ $slot }}</x-empty-state>
    </td>
</tr>

{{-- The admin text field. Its focus indicator sits on the edge (no offset), unlike studio-focus-ring on buttons. Every attribute (name, value, type, required, ...) passes straight through. --}}
{{-- Component attributes are trimmed, so a value with leading/trailing spaces loses them; form input is already trimmed by TrimStrings. --}}
<input {{ $attributes->merge(['class' => 'w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand studio-light:border-studio-field-line']) }}>

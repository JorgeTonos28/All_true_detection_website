<div class="upload-field">
    <label><span>{{ $label }}</span><input type="file" name="{{ $name }}" accept="{{ $accept ?? '.pdf,.jpg,.jpeg,.png,.webp' }}" @required($required ?? false) @if($multiple ?? false) multiple @endif data-preview-input></label>
    <div class="file-previews" data-file-previews aria-live="polite"></div>
    <button type="button" class="text-button" data-clear-files hidden>Quitar selección</button>
</div>

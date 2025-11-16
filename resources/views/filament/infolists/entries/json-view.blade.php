<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <div class="space-y-2">
        @if($getState())
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <pre class="text-sm text-gray-700 dark:text-gray-300 overflow-x-auto"><code>{{ json_encode($getState(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
            </div>
        @else
            <div class="text-gray-500 dark:text-gray-400 italic">
                لا توجد بيانات
            </div>
        @endif
    </div>
</x-dynamic-component>
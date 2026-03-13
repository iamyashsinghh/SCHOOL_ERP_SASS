<div>
    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search audit logs..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
            <div class="w-full md:w-48">
                <select wire:model.live="filterAction" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">All Actions</option>
                    @foreach($actions as $action)
                    <option value="{{ $action }}">{{ ucwords(str_replace('_', ' ', $action)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Timestamp</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">User</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Action</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Entity</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('M d, Y H:i') }}</td>
                    <td class="px-6 py-4 text-gray-900">{{ $log->user_id ?? 'System' }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                            {{ ucwords(str_replace('_', ' ', $log->action)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                    <td class="px-6 py-4 text-gray-500 max-w-xs truncate">{{ json_encode($log->data) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No audit logs found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
    </div>
</div>

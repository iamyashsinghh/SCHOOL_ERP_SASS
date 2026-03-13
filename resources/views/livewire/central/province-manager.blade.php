<div>
    @if($message)
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl">{{ $message }}</div>
    @endif

    <!-- Province Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">{{ $isEditing ? 'Edit Province' : 'Add Province' }}</h3>
        <form wire:submit.prevent="{{ $isEditing ? 'update' : 'store' }}">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" wire:model="name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Province name">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                    <input type="text" wire:model="code" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. PROV01">
                    @error('code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ministry</label>
                    <select wire:model="ministry_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select Ministry</option>
                        @foreach($ministries as $ministry)
                        <option value="{{ $ministry->id }}">{{ $ministry->name }}</option>
                        @endforeach
                    </select>
                    @error('ministry_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="mt-4 flex space-x-2">
                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium">
                    {{ $isEditing ? 'Update' : 'Create' }}
                </button>
                @if($isEditing)
                <button type="button" wire:click="resetFields" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm font-medium">Cancel</button>
                @endif
            </div>
        </form>
    </div>

    <!-- Province List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Name</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Code</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Ministry</th>
                    <th class="text-right px-6 py-3 font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($provinces as $province)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $province->name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $province->code }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $province->ministry->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <button wire:click="edit({{ $province->id }})" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Edit</button>
                        <button wire:click="delete({{ $province->id }})" wire:confirm="Delete this province?" class="text-red-600 hover:text-red-800 text-sm font-medium">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">No provinces found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

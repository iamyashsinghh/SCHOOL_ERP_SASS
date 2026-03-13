<div>
    @if($message)
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl">{{ $message }}</div>
    @endif

    <!-- Actions -->
    <div class="flex justify-between items-center mb-6">
        <div></div>
        <button wire:click="$set('isCreating', true)" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Provision New School</span>
        </button>
    </div>

    <!-- Provisioning Form -->
    @if($isCreating)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Provision New School</h3>
        <form wire:submit.prevent="provision">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">School Name *</label>
                    <input type="text" wire:model="name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. Delhi Public School">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sub-Division *</label>
                    <select wire:model="sub_division_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select Sub-Division</option>
                        @foreach($subDivisions as $sd)
                        <option value="{{ $sd->id }}">{{ $sd->name }}</option>
                        @endforeach
                    </select>
                    @error('sub_division_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domain *</label>
                    <input type="text" wire:model.live="domain" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. dps.kalkix.site">
                    @error('domain') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Timezone *</label>
                    <select wire:model="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select Timezone</option>
                        <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America/New_York (EST)</option>
                        <option value="Europe/London">Europe/London (GMT)</option>
                        <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                    </select>
                    @error('timezone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <h4 class="text-md font-semibold mt-6 mb-3 text-gray-700">Admin Account</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admin Name *</label>
                    <input type="text" wire:model="admin_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Administrator">
                    @error('admin_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admin Username *</label>
                    <input type="text" wire:model="admin_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="admin">
                    @error('admin_username') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email *</label>
                    <input type="email" wire:model="admin_email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="admin@domain.com">
                    @error('admin_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admin Password *</label>
                    <input type="password" wire:model="admin_password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="••••••••">
                    @error('admin_password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <h4 class="text-md font-semibold mt-6 mb-3 text-gray-700">Contact (Optional)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" wire:model="contact_phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" wire:model="address" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <div class="mt-6 flex space-x-3">
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition text-sm font-medium" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="provision">🚀 Start Provisioning</span>
                    <span wire:loading wire:target="provision">⏳ Provisioning...</span>
                </button>
                <button type="button" wire:click="$set('isCreating', false)" class="px-6 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm font-medium">Cancel</button>
            </div>
        </form>
    </div>
    @endif

    <!-- Edit Users Panel -->
    @if($isEditingUsers)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Edit School Users</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Select User</label>
                <select wire:model.live="selectedUserId" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select a user</option>
                    @foreach($tenantUsers as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($selectedUserId)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" wire:model="editUserName" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" wire:model="editUserEmail" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" wire:model="editUserUsername" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password (leave empty to keep)</label>
                <input type="password" wire:model="editUserPassword" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="••••••••">
            </div>
        </div>
        <div class="mt-4 flex space-x-2">
            <button wire:click="updateUser" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium">Update User</button>
            <button wire:click="closeEditUsers" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm font-medium">Cancel</button>
        </div>
        @endif
    </div>
    @endif

    <!-- Schools Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">ID</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">School</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Domain</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Sub-Division</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-500">Status</th>
                    <th class="text-right px-6 py-3 font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($schools as $school)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 text-gray-500">#{{ $school->id }}</td>
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $school->name }}</td>
                    <td class="px-6 py-4"><span class="text-indigo-600">{{ $school->domains->first()?->domain ?? '-' }}</span></td>
                    <td class="px-6 py-4 text-gray-600">{{ $school->subDivision?->name ?? '-' }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $school->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            {{ ucfirst($school->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end space-x-2">
                            <button wire:click="impersonate({{ $school->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium" title="Login as Admin">🔑 SSO</button>
                            <button wire:click="openEditUsers({{ $school->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium" title="Edit Users">👤 Users</button>
                            <button wire:click="toggleStatus({{ $school->id }})" class="text-amber-600 hover:text-amber-800 text-xs font-medium">
                                {{ $school->status === 'active' ? '⏸ Suspend' : '▶ Activate' }}
                            </button>
                            <button wire:click="deleteSchool({{ $school->id }})" wire:confirm="Are you sure? This will DELETE all data for this school!" class="text-red-600 hover:text-red-800 text-xs font-medium">🗑 Delete</button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">No schools found. Click "Provision New School" to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

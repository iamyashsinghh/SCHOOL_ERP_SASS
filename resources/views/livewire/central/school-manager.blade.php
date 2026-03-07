<div class="space-y-6">
    @if (session()->has('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('success') }}
        </div>
    @endif

    @if($message)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            {{ $message }}
        </div>
    @endif

    <div class="flex justify-between items-center">
        <h2 class="text-xl font-bold">Manage Schools</h2>
        <button wire:click="$toggle('isCreating')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            {{ $isCreating ? 'Cancel' : 'Provision New School' }}
        </button>
    </div>

    @if($isCreating)
        <div class="bg-white shadow rounded-lg p-6 animate-pulse" wire:loading.class="opacity-50">
            <h3 class="text-lg font-bold mb-4">New School Provisioning</h3>
            <form wire:submit.prevent="provision" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h4 class="font-semibold text-indigo-700 border-b">Basic Info</h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">School Name</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sub-Division</label>
                        <select wire:model="sub_division_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            <option value="">Select Sub-Division</option>
                            @foreach($subDivisions as $sd)
                                <option value="{{ $sd->id }}">{{ $sd->name }}</option>
                            @endforeach
                        </select>
                        @error('sub_division_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Primary Domain</label>
                        <input type="text" wire:model="domain" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" placeholder="school1.localhost">
                        @error('domain') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Timezone</label>
                        <select wire:model="timezone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @foreach(timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>


                <div class="md:col-span-2 space-y-4 bg-gray-50 p-4 rounded border-l-4 border-indigo-500">
                    <h4 class="font-semibold text-indigo-700 border-b">Tenant Admin Account</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Admin Name</label>
                            <input type="text" wire:model="admin_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('admin_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Admin Username</label>
                            <input type="text" wire:model="admin_username" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('admin_username') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Admin Email</label>
                            <input type="email" wire:model="admin_email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('admin_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Admin Password</label>
                            <input type="password" wire:model="admin_password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('admin_password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 flex items-center">
                        <span wire:loading class="mr-2">Provisioning...</span>
                        Start Automated Provisioning
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($isEditingUsers)
        <div class="bg-white shadow rounded-lg p-6" wire:loading.class="opacity-50">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-amber-700">Edit Tenant Users</h3>
                <button wire:click="closeEditUsers" class="text-gray-500 hover:text-gray-800 text-2xl font-bold">&times;</button>
            </div>
            <form wire:submit.prevent="updateUser" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Select User</label>
                    <select wire:model.live="selectedUserId" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                        <option value="">-- Choose User --</option>
                        @foreach($tenantUsers as $user)
                            <option value="{{ (array)$user !== $user ? $user->id : $user['id'] }}">{{ (array)$user !== $user ? $user->name : $user['name'] }} ({{ (array)$user !== $user ? $user->username : $user['username'] }} - {{ (array)$user !== $user ? $user->email : $user['email'] }})</option>
                        @endforeach
                    </select>
                    @error('selectedUserId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                @if($selectedUserId)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded border-l-4 border-amber-500">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" wire:model.defer="editUserName" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('editUserName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" wire:model.defer="editUserUsername" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('editUserUsername') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" wire:model.defer="editUserEmail" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('editUserEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Password (leave empty to keep current)</label>
                            <input type="password" wire:model.defer="editUserPassword" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('editUserPassword') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-4">
                        <button type="button" wire:click="closeEditUsers" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="bg-amber-600 text-white px-6 py-2 rounded-md hover:bg-amber-700 flex items-center">
                            <span wire:loading class="mr-2">Saving...</span>
                            Save Changes
                        </button>
                    </div>
                @endif
            </form>
        </div>
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hierarchy</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DB Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin Credentials</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($schools as $school)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $school->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $school->subDivision?->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $school->domains->first()?->domain }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $school->db_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $school->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($school->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($school->admin_username)
                                <div><strong>User:</strong> {{ $school->admin_username }}</div>
                                <div><strong>Pass:</strong> {{ $school->admin_password_reference }}</div>
                            @else
                                <span class="text-gray-400 italic">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <button wire:click="openEditUsers({{ $school->id }})" class="text-blue-600 hover:text-blue-900 font-bold border border-blue-600 px-2 py-1 rounded">
                                Edit Users
                            </button>
                            <button wire:click="impersonate({{ $school->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold border border-indigo-600 px-2 py-1 rounded">
                                Login as Admin
                            </button>
                            <button wire:click="toggleStatus({{ $school->id }})" class="{{ $school->status === 'active' ? 'text-amber-600 hover:text-amber-900' : 'text-green-600 hover:text-green-900' }}">
                                {{ $school->status === 'active' ? 'Suspend' : 'Activate' }}
                            </button>
                            <button wire:click="deleteSchool({{ $school->id }})" 
                                    wire:confirm="Are you sure you want to PERMANENTLY delete this school? All data, databases, and files will be lost forever."
                                    class="text-red-600 hover:text-red-900 font-bold">
                                Delete
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

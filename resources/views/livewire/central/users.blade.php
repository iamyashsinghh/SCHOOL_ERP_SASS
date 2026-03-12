<div class="space-y-6">
    @if (session()->has('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">User Management</h2>
        <button wire:click="openModal" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Add New User
        </button>
    </div>

    <!-- User List -->
    <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name / Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity ID</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ str_replace('_', ' ', ucfirst($user->role)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $user->entity_id ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button wire:click="edit({{ $user->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <button wire:click="delete({{ $user->id }})" onclick="return confirm('Are you sure?') || event.stopImmediatePropagation()" class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500 italic">No users found in your scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                    <h3 class="text-lg font-bold mb-4">{{ $isEditing ? 'Edit User' : 'Add User' }}</h3>
                    <form wire:submit.prevent="{{ $isEditing ? 'update' : 'store' }}" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" wire:model="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password {{ $isEditing ? '(Leave blank to keep current)' : '' }}</label>
                            <input type="password" wire:model="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                            @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Role</label>
                            <select wire:model.live="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                                <option value="">Select Role</option>
                                @if(auth('central')->user()->isPlatformOwner())
                                    <option value="platform_owner">Platform Owner</option>
                                    <option value="ministry_admin">Ministry Admin</option>
                                @endif
                                
                                @if(auth('central')->user()->isPlatformOwner() || auth('central')->user()->isMinistryAdmin())
                                    <option value="province_admin">Province Admin</option>
                                @endif

                                <option value="subdivision_admin">Sub-Division Admin</option>
                            </select>
                            @error('role') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        @if($role === 'ministry_admin' && auth('central')->user()->isPlatformOwner())
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assign Ministry</label>
                                <select wire:model="entity_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                                    <option value="">Select Ministry</option>
                                    @foreach($ministries as $min)
                                        <option value="{{ $min->id }}">{{ $min->name }}</option>
                                    @endforeach
                                </select>
                                @error('entity_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        @elseif($role === 'province_admin')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assign Province</label>
                                <select wire:model="entity_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                                    <option value="">Select Province</option>
                                    @foreach($provinces as $prov)
                                        <option value="{{ $prov->id }}">{{ $prov->name }}</option>
                                    @endforeach
                                </select>
                                @error('entity_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        @elseif($role === 'subdivision_admin')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assign Sub-Division</label>
                                <select wire:model="entity_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                                    <option value="">Select Sub-Division</option>
                                    @foreach($subDivisions as $sub)
                                        <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                    @endforeach
                                </select>
                                @error('entity_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" wire:click="$set('showModal', false)" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Cancel</button>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                                {{ $isEditing ? 'Update User' : 'Create User' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

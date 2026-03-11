<div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Schools</dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo e($schoolsCount); ?></dd>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Ministries</dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo e($ministriesCount); ?></dd>
            </div>
        </div>
        
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Pending Tasks</dt>
                <dd class="mt-1 text-3xl font-semibold text-yellow-600">0</dd>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">System Health</dt>
                <dd class="mt-1 text-3xl font-semibold text-green-600">Stable</dd>
            </div>
        </div>
    </div>

    <div class="mt-8">
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Quick Actions</h2>
            <div class="space-x-4">
                <button class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">Add New Ministry</button>
                <button class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Provision New School</button>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\PROJECTS\YASHU_MITTAL\SCHOOL_ERP_SASS\resources\views/livewire/central/dashboard.blade.php ENDPATH**/ ?>
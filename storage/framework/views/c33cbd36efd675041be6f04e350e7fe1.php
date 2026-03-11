<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title ?? 'Governance Panel'); ?></title>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="min-h-screen">
        <nav class="bg-indigo-700 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center">
                        <span class="font-bold text-xl tracking-tight">SaaS Governance</span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard('central')->check()): ?>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="<?php echo e(route('central.dashboard')); ?>" class="px-3 py-2 rounded-md text-sm font-medium <?php echo e(request()->routeIs('central.dashboard') ? 'bg-indigo-900' : 'hover:bg-indigo-600'); ?>">Dashboard</a>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth('central')->user()?->isPlatformOwner() || auth('central')->user()?->isMinistryAdmin()): ?>
                                <a href="<?php echo e(route('central.ministries')); ?>" class="px-3 py-2 rounded-md text-sm font-medium <?php echo e(request()->routeIs('central.ministries') ? 'bg-indigo-900' : 'hover:bg-indigo-600'); ?>">Ministries</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <a href="<?php echo e(route('central.schools')); ?>" class="px-3 py-2 rounded-md text-sm font-medium <?php echo e(request()->routeIs('central.schools') ? 'bg-indigo-900' : 'hover:bg-indigo-600'); ?>">Schools</a>
                            
                            <a href="<?php echo e(route('central.users')); ?>" class="px-3 py-2 rounded-md text-sm font-medium <?php echo e(request()->routeIs('central.users') ? 'bg-indigo-900' : 'hover:bg-indigo-600'); ?>">Users</a>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth('central')->user()?->isPlatformOwner()): ?>
                                <a href="<?php echo e(route('central.roles.index')); ?>" class="px-3 py-2 rounded-md text-sm font-medium <?php echo e(request()->routeIs('central.roles.index') ? 'bg-indigo-900' : 'hover:bg-indigo-600'); ?>">Roles & Permissions</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-sm mr-4"><?php echo e(auth('central')->user()?->name); ?></span>
                        <a href="<?php echo e(route('central.logout')); ?>" class="text-sm font-medium hover:underline">Logout</a>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </nav>

        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    <?php echo e($header ?? 'Dashboard'); ?>

                </h1>
            </div>
        </header>

        <main>
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <?php echo e($slot); ?>

            </div>
        </main>
    </div>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>
</html>
<?php /**PATH D:\PROJECTS\YASHU_MITTAL\SCHOOL_ERP_SASS\resources\views/layouts/central.blade.php ENDPATH**/ ?>
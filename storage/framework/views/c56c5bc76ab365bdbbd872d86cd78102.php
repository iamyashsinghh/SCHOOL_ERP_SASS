<?php if (isset($component)) { $__componentOriginal50bf70515bc668868963a2292f9e0961 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal50bf70515bc668868963a2292f9e0961 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.errors.layout','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('errors.layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <p class="text-primary text-4xl font-extrabold sm:text-5xl">404</p>
    <div class="sm:ml-6">
        <div class="sm:border-l sm:border-gray-600 sm:pl-6">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">
                <?php echo e(trans('general.errors.404_title')); ?></h1>
            <p class="mt-1 text-base text-gray-500"><?php echo e(trans('general.errors.404_description')); ?></p>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal50bf70515bc668868963a2292f9e0961)): ?>
<?php $attributes = $__attributesOriginal50bf70515bc668868963a2292f9e0961; ?>
<?php unset($__attributesOriginal50bf70515bc668868963a2292f9e0961); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal50bf70515bc668868963a2292f9e0961)): ?>
<?php $component = $__componentOriginal50bf70515bc668868963a2292f9e0961; ?>
<?php unset($__componentOriginal50bf70515bc668868963a2292f9e0961); ?>
<?php endif; ?>
<?php /**PATH D:\PROJECTS\YASHU_MITTAL\SCHOOL_ERP_SASS\resources\views/errors/404.blade.php ENDPATH**/ ?>
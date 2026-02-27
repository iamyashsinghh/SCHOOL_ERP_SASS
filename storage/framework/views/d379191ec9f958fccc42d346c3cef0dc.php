<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('config.finance.enable_amwalpay')): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('config.finance.enable_live_amwalpay_mode')): ?>
        <script src="https://checkout.amwalpg.com/js/SmartBox.js?v=1.1"></script>
    <?php else: ?>
        <script src="https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1"></script>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\PROJECTS\YASHU_MITTAL\SCHOOL_ERP_SASS\resources\views/gateways/assets/amwalpay.blade.php ENDPATH**/ ?>
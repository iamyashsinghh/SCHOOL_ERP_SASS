@if (config('config.finance.enable_amwalpay'))
    @if (config('config.finance.enable_live_amwalpay_mode'))
        <script src="https://checkout.amwalpg.com/js/SmartBox.js?v=1.1"></script>
    @else
        <script src="https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1"></script>
    @endif
@endif

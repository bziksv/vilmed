    </div>
    <div class="legal-page__nav">
        <?php $legalConfig = include $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/legal/config.php'; ?>
        <a href="<?= htmlspecialcharsbx($legalConfig['urls']['cookie']) ?>">Политика cookie</a>
        <a href="<?= htmlspecialcharsbx($legalConfig['urls']['recommendation']) ?>">Рекомендательные технологии</a>
        <a href="<?= htmlspecialcharsbx($legalConfig['urls']['personal_data']) ?>">Политика обработки персональных данных</a>
        <a href="<?= htmlspecialcharsbx($legalConfig['urls']['consent']) ?>">Согласие на обработку ПДн</a>
    </div>
</div>

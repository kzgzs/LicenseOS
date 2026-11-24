<?php
// 玄铁栈道 EquiLedger 配套 · LicenseOS 非 WP 包注入链路开发自测
// 用法：php scripts-dev/inject_roundtrip.php <zip路径>
// 断言：注入成功 → 校验 success → 篡改原文件后校验 incomplete → 恢复后 success → 一键还原后 license-os/ 消失
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$src = $argv[1] ?? '/dist/EquiLedger-1.0.0.zip';
$tmp = sys_get_temp_dir() . '/los_roundtrip_' . uniqid() . '.zip';
copy($src, $tmp);

$inj = app(App\Services\LicenseProtectionInjector::class);
$results = [];

$results['inject'] = $inj->inject($tmp, 'https://sz.djkz.top/api/v2', 'losk_ROUNDTRIP_TEST', 'equiledger', 'web_app');
$v = $inj->verifyInjection($tmp);
$results['verify_after_inject'] = $v['status'];

// 篡改一个原文件 → 完整性校验必须失败
$orig = new ZipArchive();
$orig->open($src);
$readme = $orig->getFromName('README.md');
$orig->close();
$z = new ZipArchive();
$z->open($tmp);
$z->addFromString('README.md', "tampered content\n");
$z->close();
$v2 = $inj->verifyInjection($tmp);
$results['verify_after_tamper'] = $v2['status'];

// 恢复原文件 → 校验回到 success
$z = new ZipArchive();
$z->open($tmp);
$z->addFromString('README.md', $readme);
$z->close();
$v3 = $inj->verifyInjection($tmp);
$results['verify_after_restore'] = $v3['status'];

// 一键还原 → license-os/ 整体消失
$results['uninject'] = $inj->uninject($tmp);
$z = new ZipArchive();
$z->open($tmp);
$results['clean_after_uninject'] = $z->locateName('license-os/config.json') === false;
$results['entry_count_after_uninject'] = $z->numFiles;
$z->close();
$orig = new ZipArchive();
$orig->open($src);
$results['entry_count_original'] = $orig->numFiles;
$orig->close();
@unlink($tmp);

$pass = $results['inject'] === true
    && $results['verify_after_inject'] === 'success'
    && $results['verify_after_tamper'] === 'incomplete'
    && $results['verify_after_restore'] === 'success'
    && $results['uninject'] === true
    && $results['clean_after_uninject'] === true
    && $results['entry_count_after_uninject'] === $results['entry_count_original'];
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
echo $pass ? 'ROUNDTRIP PASS' : 'ROUNDTRIP FAIL', PHP_EOL;
exit($pass ? 0 : 1);
diff --git a/load.php b/load.php
index dcd67b6c9a4058a1af5bea14c76d670dd6db622e..2064f6a4d46653bb10f17dac1ed25f9ff0d33bf5 100644
--- a/load.php
+++ b/load.php
@@ -56,26 +56,84 @@ spl_autoload_register(
                     break;
                 }
             }
         };
 
         // 1) PSR-4: \Foo\Bar -> inc/Class/Foo/Bar.php
         $relativePath = str_replace('\\', '/', $class) . '.php';
         $path = CLASS_DIR . '/' . $relativePath;
         if (is_file($path)) {
             require_once $path;
             $loadHelpers($path);
             return;
         }
 
         // 2) Legacy fallback: Some_Legacy_Class -> inc/Class/Some/Legacy/Class.php
         if (str_contains($class, '_')) {
             $legacyPath = CLASS_DIR . '/' . str_replace('_', '/', $class) . '.php';
             if (is_file($legacyPath)) {
                 require_once $legacyPath;
                 $loadHelpers($legacyPath);
                 return;
             }
         }
     },
     prepend: true
-);
\ No newline at end of file
+);
+
+/**
+ * Přesměruj na instalátor a ukonči skript.
+ */
+function cms_redirect_to_install(): never
+{
+    header('Location: install/');
+    exit;
+}
+
+/**
+ * Načti konfiguraci a ověř dostupnost databáze. Pokud chybí, přesměruj na instalátor.
+ *
+ * @return array<string,mixed>
+ */
+function cms_bootstrap_config_or_redirect(): array
+{
+    $configFile = BASE_DIR . '/config.php';
+    if (!is_file($configFile)) {
+        cms_redirect_to_install();
+    }
+
+    $config = require $configFile;
+    if (!is_array($config) || !isset($config['db'])) {
+        cms_redirect_to_install();
+    }
+
+    /** @var array<string,mixed> $config */
+    \Core\Database\Init::boot($config);
+
+    return $config;
+}
+
+/**
+ * Přesměruj na veřejnou login stránku. Pro AJAX požadavky vrať JSON odpověď.
+ */
+function cms_redirect_to_front_login(bool $success = false): never
+{
+    $target = 'login.php';
+    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
+
+    if (!$isAjax) {
+        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
+        $isAjax = str_contains($accept, 'application/json');
+    }
+
+    if ($isAjax) {
+        header('Content-Type: application/json; charset=utf-8');
+        echo json_encode([
+            'success'  => $success,
+            'redirect' => $target,
+        ], JSON_UNESCAPED_UNICODE);
+        exit;
+    }
+
+    header('Location: ' . $target);
+    exit;
+}

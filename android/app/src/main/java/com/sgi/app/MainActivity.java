package com.sgi.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import androidx.work.Constraints;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;

import com.google.firebase.messaging.FirebaseMessaging;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.TimeUnit;

public class MainActivity extends AppCompatActivity {

    public static final String APP_URL = "https://tgsgi.onrender.com/index.php";
    public static final String APP_HOST = "tgsgi.onrender.com";
    private static final int FILE_CHOOSER_RESULT_CODE = 1001;
    private static final int NOTIFICATION_PERMISSION_CODE = 1002;

    private WebView webView;
    private SwipeRefreshLayout swipeRefresh;
    private ProgressBar progressBar;
    private LinearLayout errorLayout;
    private Button btnRetry;

    private ValueCallback<Uri[]> fileUploadCallback;
    private String fcmToken = "";

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        swipeRefresh = findViewById(R.id.swipeRefresh);
        progressBar = findViewById(R.id.progressBar);
        errorLayout = findViewById(R.id.errorLayout);
        btnRetry = findViewById(R.id.btnRetry);

        setupCookies();
        setupWebViewSettings();
        setupWebViewClients();
        setupSwipeRefresh();
        setupDownloadListener();
        setupBackNavigation();

        btnRetry.setOnClickListener(v -> {
            errorLayout.setVisibility(View.GONE);
            webView.setVisibility(View.VISIBLE);
            webView.reload();
        });

        // Request Notification permission for Android 13+ (Tiramisu)
        requestNotificationPermission();

        // Initialize Firebase Cloud Messaging Token
        initFcm();

        // Schedule periodic background check
        scheduleBackgroundNotificationWorker();

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState);
        } else {
            handleIntent(getIntent());
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleIntent(intent);
    }

    private void handleIntent(Intent intent) {
        if (intent != null && intent.hasExtra("target_url")) {
            String url = intent.getStringExtra("target_url");
            if (url != null && !url.trim().isEmpty()) {
                if (!url.startsWith("http://") && !url.startsWith("https://")) {
                    url = "https://" + APP_HOST + (url.startsWith("/") ? "" : "/") + url;
                }
                webView.loadUrl(url);
                return;
            }
        }
        webView.loadUrl(APP_URL);
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                    != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(
                        this,
                        new String[]{Manifest.permission.POST_NOTIFICATIONS},
                        NOTIFICATION_PERMISSION_CODE
                );
            }
        }
    }

    private void initFcm() {
        SharedPreferences prefs = getSharedPreferences("sgi_prefs", MODE_PRIVATE);
        fcmToken = prefs.getString("fcm_token", "");

        try {
            FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
                if (task.isSuccessful() && task.getResult() != null) {
                    fcmToken = task.getResult();
                    prefs.edit().putString("fcm_token", fcmToken).apply();
                    registerFcmWithCurrentSession();
                }
            });
        } catch (Exception ignored) {}
    }

    private void scheduleBackgroundNotificationWorker() {
        try {
            Constraints constraints = new Constraints.Builder()
                    .setRequiredNetworkType(NetworkType.CONNECTED)
                    .build();

            PeriodicWorkRequest syncRequest = new PeriodicWorkRequest.Builder(
                    NotificationSyncWorker.class,
                    15, TimeUnit.MINUTES
            ).setConstraints(constraints).build();

            WorkManager.getInstance(this).enqueueUniquePeriodicWork(
                    "SgiNotificationSync",
                    ExistingPeriodicWorkPolicy.KEEP,
                    syncRequest
            );
        } catch (Exception ignored) {}
    }

    private void setupCookies() {
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true);
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebViewSettings() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setSupportZoom(true);
        settings.setBuiltInZoomControls(true);
        settings.setDisplayZoomControls(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        }

        // Custom User-Agent to let PHP know it's the SGI Android App
        String defaultUa = settings.getUserAgentString();
        settings.setUserAgentString(defaultUa + " SGI-Android-App/1.1");

        // JS Bridge for Web -> Android interaction
        webView.addJavascriptInterface(new WebAppInterface(), "Android");
    }

    public class WebAppInterface {
        @JavascriptInterface
        public String getFcmToken() {
            return fcmToken;
        }

        @JavascriptInterface
        public void showSystemNotification(String title, String body, String url) {
            NotificationHelper.showNotification(MainActivity.this, title, body, url);
        }

        @JavascriptInterface
        public void saveUserIdentifier(String identifier, String type) {
            if (identifier != null && !identifier.trim().isEmpty()) {
                final String cleanId = identifier.trim();
                final String cleanType = (type != null && !type.isEmpty()) ? type : "student";
                getSharedPreferences("sgi_prefs", MODE_PRIVATE)
                        .edit()
                        .putString("user_identifier", cleanId)
                        .putString("user_type", cleanType)
                        .apply();

                // Direct background register to ensure token is linked to roll/mentor_id immediately
                if (fcmToken != null && !fcmToken.isEmpty()) {
                    new Thread(() -> {
                        try {
                            URL url = new URL("https://" + APP_HOST + "/api/register_fcm.php");
                            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                            conn.setRequestMethod("POST");
                            conn.setDoOutput(true);
                            conn.setConnectTimeout(6000);
                            conn.setReadTimeout(6000);
                            conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
                            conn.setRequestProperty("User-Agent", "SGI-Android-App/1.1");
                            String postData = "fcm_token=" + URLEncoder.encode(fcmToken, "UTF-8") +
                                    "&roll=" + URLEncoder.encode(cleanId, "UTF-8") +
                                    "&type=" + URLEncoder.encode(cleanType, "UTF-8");
                            try (OutputStream os = conn.getOutputStream()) {
                                os.write(postData.getBytes(StandardCharsets.UTF_8));
                            }
                            conn.getResponseCode();
                            conn.disconnect();
                        } catch (Exception ignored) {}
                    }).start();
                }
            }
        }
    }

    private void setupWebViewClients() {
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();
                String host = uri.getHost();
                String scheme = uri.getScheme();

                if (scheme != null && (scheme.equals("mailto") || scheme.equals("tel") || scheme.equals("whatsapp"))) {
                    try {
                        startActivity(new Intent(Intent.ACTION_VIEW, uri));
                    } catch (ActivityNotFoundException e) {
                        Toast.makeText(MainActivity.this, "No app available for this action", Toast.LENGTH_SHORT).show();
                    }
                    return true;
                }

                // Keep SGI within WebView
                if (host != null && host.contains(APP_HOST)) {
                    return false;
                }

                // External links open in external browser
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, uri));
                } catch (Exception ignored) {
                    return false;
                }
                return true;
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                swipeRefresh.setRefreshing(false);
                progressBar.setVisibility(View.GONE);
                CookieManager.getInstance().flush();

                // Automatically register FCM token with the logged-in session
                registerFcmWithCurrentSession();
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    swipeRefresh.setRefreshing(false);
                    progressBar.setVisibility(View.GONE);
                    webView.setVisibility(View.GONE);
                    errorLayout.setVisibility(View.VISIBLE);
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                if (newProgress < 100) {
                    progressBar.setAlpha(1f);
                    progressBar.setVisibility(View.VISIBLE);
                    progressBar.setProgress(newProgress);
                } else {
                    progressBar.setProgress(100);
                    progressBar.animate()
                            .alpha(0f)
                            .setDuration(250)
                            .withEndAction(() -> progressBar.setVisibility(View.GONE));
                }
            }

            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                if (fileUploadCallback != null) {
                    fileUploadCallback.onReceiveValue(null);
                }
                fileUploadCallback = filePathCallback;

                Intent intent = fileChooserParams.createIntent();
                try {
                    startActivityForResult(intent, FILE_CHOOSER_RESULT_CODE);
                } catch (ActivityNotFoundException e) {
                    fileUploadCallback = null;
                    Toast.makeText(MainActivity.this, "Cannot open file chooser", Toast.LENGTH_SHORT).show();
                    return false;
                }
                return true;
            }
        });
    }

    private void registerFcmWithCurrentSession() {
        if (fcmToken == null || fcmToken.length() < 10) return;
        // Inject JS to notify backend and save user identifier
        String js = "(function() {" +
                "  try {" +
                "    var token = '" + fcmToken + "';" +
                "    var rollEl = document.querySelector('.profile-roll');" +
                "    var roll = (rollEl && rollEl.innerText) ? rollEl.innerText.trim() : '';" +
                "    if (roll && window.Android && window.Android.saveUserIdentifier) {" +
                "      window.Android.saveUserIdentifier(roll, roll.startsWith('M') ? 'mentor' : 'student');" +
                "    }" +
                "    if (token && window.fetch) {" +
                "      var params = 'fcm_token=' + encodeURIComponent(token);" +
                "      if (roll) params += '&roll=' + encodeURIComponent(roll) + '&type=' + (roll.startsWith('M') ? 'mentor' : 'student');" +
                "      fetch('/api/register_fcm.php', {" +
                "        method: 'POST'," +
                "        headers: {'Content-Type': 'application/x-www-form-urlencoded'}," +
                "        body: params" +
                "      }).catch(function(){});" +
                "    }" +
                "  } catch(e) {}" +
                "})();";
        webView.evaluateJavascript(js, null);
    }

    private void setupSwipeRefresh() {
        swipeRefresh.setColorSchemeColors(0xFF1A1A2E, 0xFFE94560, 0xFFF5A623);
        swipeRefresh.setOnRefreshListener(() -> webView.reload());
    }

    private void setupDownloadListener() {
        webView.setDownloadListener((url, userAgent, contentDisposition, mimetype, contentLength) -> {
            try {
                DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
                request.setMimeType(mimetype);
                String filename = URLUtil.guessFileName(url, contentDisposition, mimetype);
                request.setTitle(filename);
                request.setDescription("Downloading file from SGI...");
                request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, filename);

                DownloadManager dm = (DownloadManager) getSystemService(DOWNLOAD_SERVICE);
                if (dm != null) {
                    dm.enqueue(request);
                    Toast.makeText(this, "Downloading " + filename + "...", Toast.LENGTH_SHORT).show();
                }
            } catch (Exception e) {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                startActivity(intent);
            }
        });
    }

    private void setupBackNavigation() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack();
                } else {
                    finish();
                }
            }
        });
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, @Nullable Intent data) {
        if (requestCode == FILE_CHOOSER_RESULT_CODE) {
            if (fileUploadCallback == null) return;
            Uri[] results = null;
            if (resultCode == Activity.RESULT_OK && data != null) {
                String dataString = data.getDataString();
                if (dataString != null) {
                    results = new Uri[]{Uri.parse(dataString)};
                } else if (data.getClipData() != null) {
                    int count = data.getClipData().getItemCount();
                    results = new Uri[count];
                    for (int i = 0; i < count; i++) {
                        results[i] = data.getClipData().getItemAt(i).getUri();
                    }
                }
            }
            fileUploadCallback.onReceiveValue(results);
            fileUploadCallback = null;
            return;
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    @Override
    protected void onSaveInstanceState(@NonNull Bundle outState) {
        super.onSaveInstanceState(outState);
        webView.saveState(outState);
    }
}

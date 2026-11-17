package com.sgi.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.webkit.CookieManager;

import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.HashSet;
import java.util.Set;

public class NotificationSyncWorker extends Worker {

    public NotificationSyncWorker(@NonNull Context context, @NonNull WorkerParameters workerParams) {
        super(context, workerParams);
    }

    @NonNull
    @Override
    public Result doWork() {
        try {
            Context context = getApplicationContext();
            SharedPreferences prefs = context.getSharedPreferences("sgi_prefs", Context.MODE_PRIVATE);
            String fcmToken = prefs.getString("fcm_token", "");
            String userIdentifier = prefs.getString("user_identifier", "");
            String userType = prefs.getString("user_type", "student");

            String cookies = CookieManager.getInstance().getCookie(MainActivity.APP_URL);

            String queryParams = "fcm_token=" + fcmToken;
            if (!userIdentifier.isEmpty()) {
                queryParams += "&roll=" + userIdentifier + "&type=" + userType;
            }

            URL url = new URL("https://" + MainActivity.APP_HOST + "/api/check_notifications.php?" + queryParams);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setConnectTimeout(8000);
            conn.setReadTimeout(8000);

            if (cookies != null && !cookies.isEmpty()) {
                conn.setRequestProperty("Cookie", cookies);
            }
            conn.setRequestProperty("User-Agent", "SGI-Android-App/1.1");

            int responseCode = conn.getResponseCode();
            if (responseCode == HttpURLConnection.HTTP_OK) {
                BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) {
                    sb.append(line);
                }
                reader.close();

                JSONObject response = new JSONObject(sb.toString());
                if (response.optBoolean("success", false) && response.has("notifications")) {
                    JSONArray list = response.getJSONArray("notifications");
                    Set<String> shownIds = prefs.getStringSet("shown_notification_ids", new HashSet<>());
                    Set<String> updatedShownIds = new HashSet<>(shownIds);

                    for (int i = 0; i < list.length(); i++) {
                        JSONObject notif = list.getJSONObject(i);
                        String id = notif.optString("id", "");
                        if (!id.isEmpty() && !shownIds.contains(id)) {
                            String title = notif.optString("title", "SGI Notification");
                            String message = notif.optString("message", "");
                            String link = notif.optString("link", "");

                            NotificationHelper.showNotification(context, title, message, link);
                            updatedShownIds.add(id);
                        }
                    }

                    prefs.edit().putStringSet("shown_notification_ids", updatedShownIds).apply();
                }
            }
            conn.disconnect();
            return Result.success();
        } catch (Exception e) {
            return Result.retry();
        }
    }
}

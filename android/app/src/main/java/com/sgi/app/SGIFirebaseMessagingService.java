package com.sgi.app;

import android.content.SharedPreferences;
import androidx.annotation.NonNull;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.util.Map;

public class SGIFirebaseMessagingService extends FirebaseMessagingService {

    @Override
    public void onNewToken(@NonNull String token) {
        super.onNewToken(token);
        SharedPreferences prefs = getSharedPreferences("sgi_prefs", MODE_PRIVATE);
        prefs.edit().putString("fcm_token", token).putBoolean("fcm_token_synced", false).apply();
    }

    @Override
    public void onMessageReceived(@NonNull RemoteMessage remoteMessage) {
        super.onMessageReceived(remoteMessage);

        String title = "SGI Alert";
        String body = "";
        String targetUrl = "";

        if (remoteMessage.getNotification() != null) {
            if (remoteMessage.getNotification().getTitle() != null) {
                title = remoteMessage.getNotification().getTitle();
            }
            if (remoteMessage.getNotification().getBody() != null) {
                body = remoteMessage.getNotification().getBody();
            }
        }

        Map<String, String> data = remoteMessage.getData();
        if (data != null && !data.isEmpty()) {
            if (data.containsKey("title") && data.get("title") != null) {
                title = data.get("title");
            }
            if (data.containsKey("body") && data.get("body") != null) {
                body = data.get("body");
            } else if (data.containsKey("message") && data.get("message") != null) {
                body = data.get("message");
            }
            if (data.containsKey("url") && data.get("url") != null) {
                targetUrl = data.get("url");
            } else if (data.containsKey("link") && data.get("link") != null) {
                targetUrl = data.get("link");
            }
        }

        if (body != null && !body.trim().isEmpty()) {
            NotificationHelper.showNotification(this, title, body, targetUrl);
        }
    }
}

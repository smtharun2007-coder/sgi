package com.sgi.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;

import androidx.core.app.NotificationCompat;

public class NotificationHelper {

    public static final String CHANNEL_ID = "sgi_system_notifications";
    public static final String CHANNEL_NAME = "SGI Alerts & Notifications";
    public static final String CHANNEL_DESC = "Notifications for SGI attendance, LEAP training, test marks, and approvals";

    public static void showNotification(Context context, String title, String body, String targetUrl) {
        if (title == null || title.trim().isEmpty()) {
            title = "SGI Notification";
        }
        if (body == null) {
            body = "";
        }

        NotificationManager notificationManager =
                (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
        if (notificationManager == null) return;

        Uri defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    CHANNEL_NAME,
                    NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription(CHANNEL_DESC);
            channel.enableVibration(true);
            channel.setVibrationPattern(new long[]{0, 350, 150, 350});

            AudioAttributes audioAttributes = new AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .build();
            channel.setSound(defaultSoundUri, audioAttributes);

            notificationManager.createNotificationChannel(channel);
        }

        Intent intent = new Intent(context, MainActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        if (targetUrl != null && !targetUrl.trim().isEmpty()) {
            intent.putExtra("target_url", targetUrl.trim());
        }

        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }

        int requestCode = (int) (System.currentTimeMillis() % 100000);
        PendingIntent pendingIntent = PendingIntent.getActivity(context, requestCode, intent, flags);

        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_launcher_foreground)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setSound(defaultSoundUri)
                .setVibrate(new long[]{0, 350, 150, 350})
                .setAutoCancel(true)
                .setContentIntent(pendingIntent);

        int notificationId = (int) (System.currentTimeMillis() % Integer.MAX_VALUE);
        notificationManager.notify(notificationId, builder.build());
    }
}

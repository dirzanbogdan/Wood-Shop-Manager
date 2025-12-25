import 'package:flutter/material.dart';

import 'pages/home_page.dart';
import 'pages/login_page.dart';
import 'services/session_store.dart';

void main() {
  runApp(const WsmApp());
}

class WsmApp extends StatelessWidget {
  const WsmApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'WSM Mobile',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.green),
        useMaterial3: true,
      ),
      home: FutureBuilder(
        future: SessionStore.instance.hasToken(),
        builder: (context, snapshot) {
          final hasToken = snapshot.data == true;
          if (snapshot.connectionState != ConnectionState.done) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }
          return hasToken ? const HomePage() : const LoginPage();
        },
      ),
    );
  }
}

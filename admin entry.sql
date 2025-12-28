-- Insert admin with password: @dm!n@!@#
INSERT INTO admins (username, password) VALUES (
    'admin',
    '$2y$10$RRzE7cRZaoujebDVSayRI.6inDHTM5ltW.IqTrUJkO2.uvqRuJMLu'
);

-- Or generate your own hash:
-- Go to https://phppasswordhash.com/ or use PHP:
-- echo password_hash('your_password_here', PASSWORD_DEFAULT);
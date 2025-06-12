# Two-Factor Authentication

The ArtisanPack UI CMS Framework provides a robust email-based Two-Factor Authentication (2FA) system to enhance the security of your content management system. This document explains the feature, its components, and how it works.

## What is Two-Factor Authentication?

Two-Factor Authentication adds an extra layer of security to the login process by requiring users to provide two different authentication factors:
1. Something they know (password)
2. Something they have (a temporary code sent to their email)

This significantly improves security because even if a password is compromised, an attacker would still need access to the user's email account to complete the login process.

## How Email-Based 2FA Works in the CMS Framework

The email-based 2FA system in the ArtisanPack UI CMS Framework follows this process:

1. **User Login**: A user enters their username and password.
2. **Code Generation**: Upon successful password verification, a 6-digit numeric code is generated.
3. **Code Delivery**: The code is sent to the user's registered email address.
4. **Code Verification**: The user enters the code on a verification page.
5. **Authentication Completion**: If the code is correct and hasn't expired, the user is granted access.

## Key Components

The 2FA system consists of several key components:

### Database Fields

The following fields are added to the users table:
- `two_factor_code`: Stores the current 2FA code
- `two_factor_expires_at`: Timestamp when the current code expires
- `two_factor_enabled_at`: Timestamp when 2FA was enabled for the current session

### TwoFactorAuthManager

This class manages all aspects of the 2FA process:
- Generating secure random numeric codes
- Storing codes in the database
- Sending codes via email
- Verifying entered codes
- Enabling/disabling 2FA for users

### TwoFactorAuthenticatable Trait

This trait is applied to the User model and provides methods for:
- Checking if 2FA is enabled for a user
- Verifying if a 2FA code has expired
- Setting and clearing 2FA data

### TwoFactorCodeNotification

This notification class handles sending the 2FA code to the user's email with:
- A clear subject line
- The 2FA code
- Information about code expiration
- Security advice

## Security Features

The 2FA implementation includes several security features:

### Code Expiration

All 2FA codes expire after 5 minutes, limiting the window of opportunity for attackers.

### One-Time Use

Each code can only be used once. After successful verification, the code is cleared from the database.

### Input Sanitization

All user inputs are sanitized to prevent injection attacks.

## User Experience

The 2FA system is designed to balance security with usability:

- **Clear Instructions**: Users receive clear instructions in the email with their 2FA code.
- **Resend Option**: If a user doesn't receive the code, they can request a new one.
- **Session Persistence**: Once authenticated, users don't need to re-enter 2FA codes for the duration of their session.

## Integration with Laravel

The 2FA system integrates seamlessly with Laravel's authentication system:
- Works with Laravel's built-in authentication
- Uses Laravel's notification system for sending emails
- Leverages middleware for enforcing 2FA verification

## Customization Options

The 2FA system can be customized in several ways:
- Code length (default is 6 digits)
- Code expiration time (default is 5 minutes)
- Email template and messaging
- Enforcement policies (which users require 2FA)

## Best Practices

For optimal security and user experience:
1. Encourage all users to enable 2FA
2. Ensure your email delivery system is reliable
3. Provide clear instructions to users about the 2FA process
4. Consider implementing backup methods for account recovery

## Conclusion

The email-based Two-Factor Authentication system in the ArtisanPack UI CMS Framework provides a significant security enhancement with minimal user friction. By requiring both a password and access to the user's email account, it effectively protects against unauthorized access even if passwords are compromised.
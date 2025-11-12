# Contributing to PCAP Network Analyzer

Thank you for your interest in contributing to PCAP Network Analyzer! This document provides guidelines and instructions for contributing.

## Code of Conduct

- Be respectful and considerate
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Respect different viewpoints and experiences

## How to Contribute

### Reporting Bugs

1. **Check existing issues** to see if the bug has already been reported
2. **Create a new issue** with:
   - Clear, descriptive title
   - Steps to reproduce
   - Expected vs actual behavior
   - PHP version and environment details
   - Relevant error messages or logs

### Suggesting Features

1. **Check existing issues** for similar feature requests
2. **Create a new issue** with:
   - Clear description of the feature
   - Use case and benefits
   - Possible implementation approach (if you have ideas)

### Pull Requests

1. **Fork the repository**
2. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes**:
   - Follow coding standards (PSR-12 for PHP)
   - Add comments for complex logic
   - Update documentation as needed
4. **Test your changes**:
   - Test with various PCAP files
   - Check for errors in browser console
   - Verify API endpoints work correctly
5. **Commit your changes**:
   ```bash
   git commit -m "Add: Description of your changes"
   ```
   Use clear, descriptive commit messages.
6. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```
7. **Create a Pull Request**:
   - Provide a clear description
   - Reference related issues
   - Include screenshots if UI changes

## Coding Standards

### PHP

- Follow **PSR-12** coding standards
- Use meaningful variable and function names
- Add PHPDoc comments for functions and classes
- Keep functions focused and single-purpose
- Handle errors gracefully

### JavaScript

- Use meaningful variable names
- Add comments for complex logic
- Follow consistent indentation (2 or 4 spaces)
- Use modern ES6+ features where appropriate

### CSS

- Use consistent naming conventions
- Keep styles organized and commented
- Use CSS variables for colors and spacing
- Ensure responsive design

## Project Structure

```
agila-v2/
├── api/              # API endpoints
├── assets/           # CSS and JavaScript
├── config/           # Configuration and core classes
├── uploads/          # User data (gitignored)
├── index.php         # Main dashboard
└── upload.php        # Upload interface
```

## Testing

Before submitting a PR, please test:

1. **File Upload**: Upload various PCAP/PCAPNG files
2. **Processing**: Verify files process correctly
3. **Playback**: Test all playback controls
4. **Map Display**: Verify markers appear correctly
5. **Error Handling**: Test error scenarios
6. **Browser Compatibility**: Test in Chrome, Firefox, Safari

## Documentation

- Update README.md for user-facing changes
- Update API documentation for endpoint changes
- Add inline comments for complex code
- Update this file if contributing guidelines change

## Questions?

Feel free to open an issue with the `question` label if you need help or clarification.

Thank you for contributing! 🎉


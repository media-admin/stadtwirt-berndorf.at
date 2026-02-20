# Media Lab Starter Kit - Tests

## Quick Start
```bash
# Run all smoke tests
./tests/run-tests.sh
```

## Test Levels

### Smoke Tests (Current)
- Fast execution (< 30 seconds)
- Checks basic functionality
- No external dependencies
- WP-CLI based

### Integration Tests (Future)
- Shortcode rendering
- AJAX endpoint responses
- Database operations

### Unit Tests (Future)
- PHPUnit setup
- Isolated component tests

## Requirements

- WP-CLI installed
- WordPress site running
- Plugins activated

## CI/CD Integration

Tests can be run in GitHub Actions:
```yaml
- name: Run Tests
  run: ./tests/run-tests.sh
```

## Adding Tests

Edit `tests/run-tests.sh` and add:
```bash
run_test "Test Name" "wp eval 'exit(condition ? 0 : 1);'"
```

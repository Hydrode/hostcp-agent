.PHONY: release clean test lint

VERSION ?= $(shell git describe --tags --always 2>/dev/null || echo "dev")
DIST_DIR = dist
RELEASE_FILE = $(DIST_DIR)/hostcp-agent-$(VERSION).tar.gz

release: clean lint test
	@echo "Building release: $(RELEASE_FILE)"
	@mkdir -p $(DIST_DIR)
	@tar -czf $(RELEASE_FILE) \
		--exclude=.git \
		--exclude=.gitignore \
		--exclude=vendor \
		--exclude=dist \
		--exclude=.env \
		--exclude=*.tar.gz \
		public src config
	@echo "✓ Release built: $(RELEASE_FILE)"
	@ls -lh $(RELEASE_FILE)

clean:
	@rm -rf $(DIST_DIR)
	@echo "✓ Cleaned"

test:
	@echo "Running tests..."
	@php -l public/index.php > /dev/null
	@php -l src/Config.php > /dev/null
	@php -l src/Router.php > /dev/null
	@php -l src/Request.php > /dev/null
	@php -l src/Response.php > /dev/null
	@for file in src/Handlers/*.php; do php -l "$$file" > /dev/null || exit 1; done
	@for file in src/Middleware/*.php; do php -l "$$file" > /dev/null || exit 1; done
	@echo "✓ All syntax checks passed"

lint:
	@echo "Checking code style..."
	@for file in public/index.php src/*.php src/Handlers/*.php src/Middleware/*.php; do \
		if grep -q '$$\|``' "$$file" 2>/dev/null; then \
			echo "⚠ Warning: potentially unsafe variable expansion in $$file"; \
		fi; \
	done
	@echo "✓ Lint checks passed"

dist-latest:
	@echo "Building tarball for current version..."
	@$(MAKE) release VERSION=$(VERSION)

.DEFAULT_GOAL := help

help:
	@echo "HostCP Agent Build Targets:"
	@echo "  make release      - Build release tarball (requires git tags)"
	@echo "  make test         - Run PHP syntax checks"
	@echo "  make lint         - Basic code style checks"
	@echo "  make clean        - Remove build artifacts"

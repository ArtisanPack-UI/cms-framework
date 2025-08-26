<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/**
 * Component scaffold command
 * 
 * This command generates reusable UI components for the CMS.
 * It creates Blade components, view components, and Livewire components.
 */
class ComponentScaffoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:make:component 
                           {name : The component name}
                           {--type=blade : Component type (blade, view, livewire)}
                           {--namespace= : Component namespace}
                           {--force : Overwrite existing component}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new CMS component (Blade, View, or Livewire)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $name = $this->argument('name');
            $type = $this->option('type');
            $namespace = $this->option('namespace');
            
            $this->info("Creating {$type} component: {$name}");
            
            // Validate component type
            if (!$this->validateComponentType($type)) {
                return Command::FAILURE;
            }
            
            // Create component based on type
            switch ($type) {
                case 'blade':
                    return $this->createBladeComponent($name, $namespace);
                    
                case 'view':
                    return $this->createViewComponent($name, $namespace);
                    
                case 'livewire':
                    return $this->createLivewireComponent($name, $namespace);
                    
                default:
                    $this->error("Invalid component type: {$type}");
                    return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('Error creating component: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Validate component type
     */
    private function validateComponentType(string $type): bool
    {
        $validTypes = ['blade', 'view', 'livewire'];
        
        if (!in_array($type, $validTypes)) {
            $this->error("Invalid component type '{$type}'.");
            $this->info('Valid types: ' . implode(', ', $validTypes));
            return false;
        }
        
        return true;
    }
    
    /**
     * Create Blade component
     */
    private function createBladeComponent(string $name, ?string $namespace): int
    {
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        
        // Define paths
        $componentPath = $this->getComponentPath($namespace);
        $viewPath = $this->getViewPath($namespace);
        
        // Check if component exists
        $componentFile = "{$componentPath}/{$studlyName}.php";
        $viewFile = "{$viewPath}/{$kebabName}.blade.php";
        
        if ((File::exists($componentFile) || File::exists($viewFile)) && !$this->option('force')) {
            $this->error("Component '{$name}' already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }
        
        // Create directories
        File::makeDirectory(dirname($componentFile), 0755, true);
        File::makeDirectory(dirname($viewFile), 0755, true);
        
        // Create component class
        $this->createBladeComponentClass($componentFile, $studlyName, $kebabName, $namespace);
        
        // Create component view
        $this->createBladeComponentView($viewFile, $studlyName);
        
        $this->info("✅ Blade component '{$name}' created successfully!");
        $this->info("   Class: {$componentFile}");
        $this->info("   View: {$viewFile}");
        $this->info("   Usage: <x-{$kebabName} />");
        
        return Command::SUCCESS;
    }
    
    /**
     * Create View component
     */
    private function createViewComponent(string $name, ?string $namespace): int
    {
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        
        // Define paths
        $componentPath = $this->getComponentPath($namespace);
        $viewPath = $this->getViewPath($namespace);
        
        // Check if component exists
        $componentFile = "{$componentPath}/{$studlyName}.php";
        $viewFile = "{$viewPath}/{$kebabName}.blade.php";
        
        if ((File::exists($componentFile) || File::exists($viewFile)) && !$this->option('force')) {
            $this->error("Component '{$name}' already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }
        
        // Create directories
        File::makeDirectory(dirname($componentFile), 0755, true);
        File::makeDirectory(dirname($viewFile), 0755, true);
        
        // Create component class
        $this->createViewComponentClass($componentFile, $studlyName, $kebabName, $namespace);
        
        // Create component view
        $this->createViewComponentView($viewFile, $studlyName);
        
        $this->info("✅ View component '{$name}' created successfully!");
        $this->info("   Class: {$componentFile}");
        $this->info("   View: {$viewFile}");
        $this->info("   Usage: <x-{$kebabName} />");
        
        return Command::SUCCESS;
    }
    
    /**
     * Create Livewire component
     */
    private function createLivewireComponent(string $name, ?string $namespace): int
    {
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        
        // Define paths
        $componentPath = app_path('Livewire');
        $viewPath = resource_path('views/livewire');
        
        if ($namespace) {
            $componentPath .= '/' . str_replace('\\', '/', $namespace);
            $viewPath .= '/' . Str::kebab($namespace);
        }
        
        // Check if component exists
        $componentFile = "{$componentPath}/{$studlyName}.php";
        $viewFile = "{$viewPath}/{$kebabName}.blade.php";
        
        if ((File::exists($componentFile) || File::exists($viewFile)) && !$this->option('force')) {
            $this->error("Component '{$name}' already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }
        
        // Create directories
        File::makeDirectory(dirname($componentFile), 0755, true);
        File::makeDirectory(dirname($viewFile), 0755, true);
        
        // Create component class
        $this->createLivewireComponentClass($componentFile, $studlyName, $namespace);
        
        // Create component view
        $this->createLivewireComponentView($viewFile, $studlyName);
        
        $livewireTag = $namespace ? Str::kebab($namespace) . '.' . $kebabName : $kebabName;
        
        $this->info("✅ Livewire component '{$name}' created successfully!");
        $this->info("   Class: {$componentFile}");
        $this->info("   View: {$viewFile}");
        $this->info("   Usage: <livewire:{$livewireTag} />");
        
        return Command::SUCCESS;
    }
    
    /**
     * Get component class path
     */
    private function getComponentPath(?string $namespace): string
    {
        $basePath = app_path('View/Components');
        
        if ($namespace) {
            $basePath .= '/' . str_replace('\\', '/', $namespace);
        }
        
        return $basePath;
    }
    
    /**
     * Get component view path
     */
    private function getViewPath(?string $namespace): string
    {
        $basePath = resource_path('views/components');
        
        if ($namespace) {
            $basePath .= '/' . Str::kebab($namespace);
        }
        
        return $basePath;
    }
    
    /**
     * Create Blade component class
     */
    private function createBladeComponentClass(string $filePath, string $studlyName, string $kebabName, ?string $namespace): void
    {
        $namespaceDeclaration = $namespace ? "App\\View\\Components\\{$namespace}" : "App\\View\\Components";
        
        $content = "<?php

namespace {$namespaceDeclaration};

use Illuminate\\Contracts\\View\\View;
use Illuminate\\View\\Component;

/**
 * {$studlyName} Blade Component
 * 
 * A reusable Blade component for the CMS.
 */
class {$studlyName} extends Component
{
    /**
     * Component properties
     */
    public string \$title;
    public string \$class;
    public bool \$show;
    
    /**
     * Create a new component instance.
     */
    public function __construct(
        string \$title = '',
        string \$class = '',
        bool \$show = true
    ) {
        \$this->title = \$title;
        \$this->class = \$class;
        \$this->show = \$show;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.{$kebabName}');
    }
}";
        
        File::put($filePath, $content);
    }
    
    /**
     * Create Blade component view
     */
    private function createBladeComponentView(string $filePath, string $studlyName): void
    {
        $content = "{{-- {$studlyName} Component --}}
@if(\$show)
<div class=\"cms-component {{ \$class }}\" {{ \$attributes }}>
    @if(\$title)
        <h3 class=\"component-title\">{{ \$title }}</h3>
    @endif
    
    <div class=\"component-content\">
        {{ \$slot }}
    </div>
</div>
@endif";
        
        File::put($filePath, $content);
    }
    
    /**
     * Create View component class
     */
    private function createViewComponentClass(string $filePath, string $studlyName, string $kebabName, ?string $namespace): void
    {
        $namespaceDeclaration = $namespace ? "App\\View\\Components\\{$namespace}" : "App\\View\\Components";
        
        $content = "<?php

namespace {$namespaceDeclaration};

use Illuminate\\Contracts\\View\\View;
use Illuminate\\View\\Component;

/**
 * {$studlyName} View Component
 * 
 * A reusable view component for the CMS with enhanced functionality.
 */
class {$studlyName} extends Component
{
    /**
     * Component data
     */
    public array \$data;
    public string \$variant;
    public bool \$loading;
    
    /**
     * Create a new component instance.
     */
    public function __construct(
        array \$data = [],
        string \$variant = 'default',
        bool \$loading = false
    ) {
        \$this->data = \$data;
        \$this->variant = \$variant;
        \$this->loading = \$loading;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.{$kebabName}', [
            'componentData' => \$this->data,
            'isLoading' => \$this->loading,
        ]);
    }
    
    /**
     * Determine if the component should be rendered.
     */
    public function shouldRender(): bool
    {
        return !empty(\$this->data) || !empty(\$this->slot);
    }
}";
        
        File::put($filePath, $content);
    }
    
    /**
     * Create View component view
     */
    private function createViewComponentView(string $filePath, string $studlyName): void
    {
        $content = "{{-- {$studlyName} View Component --}}
@if(\$shouldRender ?? true)
<div class=\"cms-view-component variant-{{ \$variant }}\" {{ \$attributes }}>
    @if(\$isLoading)
        <div class=\"loading-spinner\">
            <div class=\"spinner\"></div>
            <span>Loading...</span>
        </div>
    @else
        <div class=\"component-body\">
            @if(!empty(\$componentData))
                <div class=\"component-data\">
                    @foreach(\$componentData as \$key => \$value)
                        <div class=\"data-item\">
                            <strong>{{ ucfirst(\$key) }}:</strong> {{ \$value }}
                        </div>
                    @endforeach
                </div>
            @endif
            
            @if(\$slot->isNotEmpty())
                <div class=\"component-content\">
                    {{ \$slot }}
                </div>
            @endif
        </div>
    @endif
</div>
@endif";
        
        File::put($filePath, $content);
    }
    
    /**
     * Create Livewire component class
     */
    private function createLivewireComponentClass(string $filePath, string $studlyName, ?string $namespace): void
    {
        $namespaceDeclaration = $namespace ? "App\\Livewire\\{$namespace}" : "App\\Livewire";
        
        $content = "<?php

namespace {$namespaceDeclaration};

use Livewire\\Component;

/**
 * {$studlyName} Livewire Component
 * 
 * An interactive Livewire component for the CMS.
 */
class {$studlyName} extends Component
{
    /**
     * Component properties
     */
    public string \$message = '';
    public array \$items = [];
    public bool \$isVisible = true;
    
    /**
     * Component mount
     */
    public function mount(): void
    {
        \$this->message = 'Hello from {$studlyName}!';
        \$this->items = [];
    }
    
    /**
     * Add new item
     */
    public function addItem(string \$item): void
    {
        if (!empty(\$item)) {
            \$this->items[] = [
                'id' => uniqid(),
                'text' => \$item,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ];
            
            \$this->message = 'Item added successfully!';
        }
    }
    
    /**
     * Remove item
     */
    public function removeItem(string \$itemId): void
    {
        \$this->items = array_filter(\$this->items, fn(\$item) => \$item['id'] !== \$itemId);
        \$this->message = 'Item removed!';
    }
    
    /**
     * Toggle visibility
     */
    public function toggleVisibility(): void
    {
        \$this->isVisible = !\$this->isVisible;
    }
    
    /**
     * Clear all items
     */
    public function clearAll(): void
    {
        \$this->items = [];
        \$this->message = 'All items cleared!';
    }
    
    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.{$studlyName}');
    }
}";
        
        File::put($filePath, $content);
    }
    
    /**
     * Create Livewire component view
     */
    private function createLivewireComponentView(string $filePath, string $studlyName): void
    {
        $kebabName = Str::kebab($studlyName);
        
        $content = "{{-- {$studlyName} Livewire Component --}}
<div class=\"livewire-component {$kebabName}\">
    <div class=\"component-header\">
        <h4 class=\"component-title\">{$studlyName} Component</h4>
        <button wire:click=\"toggleVisibility\" class=\"btn btn-sm\">
            {{ \$isVisible ? 'Hide' : 'Show' }}
        </button>
    </div>
    
    @if(\$isVisible)
        <div class=\"component-content\">
            @if(\$message)
                <div class=\"alert alert-info\" role=\"alert\">
                    {{ \$message }}
                </div>
            @endif
            
            <div class=\"input-group mb-3\">
                <input type=\"text\" wire:model=\"newItem\" wire:keydown.enter=\"addItem(\$event.target.value)\" 
                       class=\"form-control\" placeholder=\"Add new item...\">
                <button wire:click=\"addItem(\$refs.newItemInput.value)\" class=\"btn btn-primary\">
                    Add Item
                </button>
            </div>
            
            @if(count(\$items) > 0)
                <div class=\"items-list\">
                    <div class=\"d-flex justify-content-between align-items-center mb-2\">
                        <h6>Items ({{ count(\$items) }})</h6>
                        <button wire:click=\"clearAll\" class=\"btn btn-sm btn-outline-danger\">
                            Clear All
                        </button>
                    </div>
                    
                    <ul class=\"list-group\">
                        @foreach(\$items as \$item)
                            <li class=\"list-group-item d-flex justify-content-between align-items-center\">
                                <div>
                                    <strong>{{ \$item['text'] }}</strong>
                                    <small class=\"text-muted d-block\">{{ \$item['created_at'] }}</small>
                                </div>
                                <button wire:click=\"removeItem('{{ \$item['id'] }}')\" 
                                        class=\"btn btn-sm btn-outline-danger\">
                                    Remove
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class=\"text-muted\">No items added yet.</p>
            @endif
        </div>
    @endif
</div>";
        
        File::put($filePath, $content);
    }
}
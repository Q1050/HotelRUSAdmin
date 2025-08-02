import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import PrimaryButton from '@/components/PrimaryButton';
import TextInput from '@/components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        formality: '',
        firstName: '',
        lastName: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <form onSubmit={submit} className="grid md:grid-cols-2 gap-4">
                <div className="space-y-2">
                    <InputLabel htmlFor="formality" value="Title" />
                    <select
                        id="formality"
                        name="formality"
                        value={data.formality}
                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        onChange={(e) => setData('formality', e.target.value)}
                        required
                    >
                        <option value="">Select a title</option>
                        <option value="Mr">Mr</option>
                        <option value="Mrs">Mrs</option>
                        <option value="Ms">Ms</option>
                        <option value="Dr">Dr</option>
                    </select>
                    <InputError message={errors.formality} className="mt-2" />
                </div>

                <div className="space-y-2">
                    <InputLabel htmlFor="firstName" value="First Name" />
                    <TextInput
                        id="firstName"
                        name="firstName"
                        value={data.firstName}
                        className="mt-1 block w-full"
                        autoComplete="given-name"
                        onChange={(e) => setData('firstName', e.target.value)}
                        required
                    />
                    <InputError message={errors.firstName} className="mt-2" />
                </div>

                <div className="space-y-2">
                    <InputLabel htmlFor="lastName" value="Last Name" />
                    <TextInput
                        id="lastName"
                        name="lastName"
                        value={data.lastName}
                        className="mt-1 block w-full"
                        autoComplete="family-name"
                        onChange={(e) => setData('lastName', e.target.value)}
                        required
                    />
                    <InputError message={errors.lastName} className="mt-2" />
                </div>

                <div className="space-y-2">
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="email"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="space-y-2">
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="space-y-2">
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm Password"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />
                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="md:col-span-2 flex items-center justify-end space-x-4">
                    <Link
                        href={route('login')}
                        className="text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        Already registered?
                    </Link>
                    <PrimaryButton disabled={processing}>
                        Register
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}

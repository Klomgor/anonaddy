export async function validateCustomEmail(email) {
  const response = await window.axios.post(route('email.validate'), { email })

  return response.data.valid === true
}

export async function validateCustomEmailWithErrors(email) {
  try {
    await validateCustomEmail(email)

    return {
      valid: true,
      message: null,
    }
  } catch (error) {
    return {
      valid: false,
      message: error.response?.data?.errors?.email?.[0] ?? 'Valid email required',
    }
  }
}
